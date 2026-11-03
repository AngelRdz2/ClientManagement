<?php

namespace App\Livewire\Conversations;

use App\Enums\ConversationStatus;
use App\Enums\MessageStatus;
use App\Models\Conversation;
use App\Models\User;
use App\Services\Conversations\ConversationService;
use App\Support\Permissions;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use RuntimeException;

#[Title('Conversaciones')]
class Inbox extends Component
{
    #[Url(except: 'open')]
    public string $filter = 'open';

    #[Url(as: 'q', except: '')]
    public string $search = '';

    #[Url(as: 'c')]
    public ?int $selectedId = null;

    public string $body = '';

    public bool $noteMode = false;

    public string $assignTo = '';

    public int $limit = 40;

    public function mount(): void
    {
        $this->authorize('conversations.attend');
        if ($this->filter === 'pending' || request('filter') === 'pending') {
            $this->filter = 'pending';
        }
        if ($this->selectedId) {
            $this->select($this->selectedId);
        }
    }

    public function select(int $id): void
    {
        $conversation = Conversation::findOrFail($id);
        $this->selectedId = $conversation->id;
        $this->reset('body', 'noteMode', 'assignTo');

        if ($conversation->assigned_to === null || $conversation->assigned_to === auth()->id()) {
            $conversation->forceFill(['unread_count' => 0])->save();
        }
    }

    protected function selected(): ?Conversation
    {
        return $this->selectedId ? Conversation::find($this->selectedId) : null;
    }

    public function send(ConversationService $service): void
    {
        $this->validate(['body' => ['required', 'string', 'max:4000']], attributes: ['body' => 'mensaje']);
        $conversation = $this->selected();
        if (! $conversation) {
            return;
        }

        if ($this->noteMode) {
            $service->addNote($conversation, auth()->user(), trim($this->body));
            $this->reset('body');

            return;
        }

        if (! auth()->user()->can(Permissions::MESSAGES_SEND)) {
            $this->addError('body', 'No tienes permiso para enviar mensajes.');

            return;
        }

        try {
            $message = $service->sendAgentMessage($conversation, auth()->user(), trim($this->body));
            $this->reset('body');
            if ($message->status === MessageStatus::Failed) {
                $this->dispatch('toast', type: 'error', message: 'El mensaje no se pudo entregar: '.$message->error_message);
            }
        } catch (RuntimeException $e) {
            $this->addError('body', $e->getMessage());
        }
    }

    public function take(ConversationService $service): void
    {
        if ($c = $this->selected()) {
            $service->take($c, auth()->user());
            $this->dispatch('toast', type: 'success', message: 'Ahora atiendes esta conversación');
        }
    }

    public function assign(ConversationService $service): void
    {
        abort_unless($this->canAssign(), 403);
        $agent = User::active()->find($this->assignTo);
        if (! $agent || ! ($c = $this->selected())) {
            return;
        }
        try {
            $service->assign($c, $agent, auth()->user());
            $this->dispatch('toast', type: 'success', message: "Asignada a {$agent->name}");
        } catch (RuntimeException $e) {
            $this->dispatch('alert', type: 'error', title: 'No se pudo asignar', message: $e->getMessage());
        }
        $this->reset('assignTo');
    }

    public function returnToBot(ConversationService $service): void
    {
        if ($c = $this->selected()) {
            $service->returnToBot($c, auth()->user());
            $this->dispatch('toast', type: 'success', message: 'Conversación devuelta al bot');
        }
    }

    public function close(ConversationService $service): void
    {
        if ($c = $this->selected()) {
            $service->close($c, auth()->user());
            $this->dispatch('toast', type: 'success', message: 'Conversación cerrada');
        }
    }

    public function reopen(ConversationService $service): void
    {
        if ($c = $this->selected()) {
            $service->reopen($c, auth()->user());
        }
    }

    public function loadMore(): void
    {
        $this->limit += 40;
    }

    protected function canAssign(): bool
    {
        return auth()->user()->hasAnyRole([Permissions::ROLE_ADMIN, Permissions::ROLE_SUPERVISOR]);
    }

    protected function listQuery(): Builder
    {
        $userId = auth()->id();

        return Conversation::query()
            ->when($this->filter === 'open', fn ($q) => $q->open())
            ->when($this->filter === 'pending', fn ($q) => $q->where('status', ConversationStatus::PendingAgent))
            ->when($this->filter === 'mine', fn ($q) => $q->where('assigned_to', $userId)->open())
            ->when($this->filter === 'assigned', fn ($q) => $q->whereIn('status', [ConversationStatus::Assigned, ConversationStatus::InProgress]))
            ->when($this->filter === 'bot', fn ($q) => $q->where('status', ConversationStatus::Bot))
            ->when($this->filter === 'closed', fn ($q) => $q->where('status', ConversationStatus::Closed))
            ->when($this->search !== '', function ($q) {
                $digits = preg_replace('/\D/', '', $this->search);
                $q->where(fn ($w) => $w->where('contact_name', 'like', "%{$this->search}%")
                    ->orWhereHas('customer', fn ($c) => $c->search($this->search))
                    ->when(strlen($digits) >= 3, fn ($w2) => $w2->orWhere('phone', 'like', "%{$digits}%")));
            });
    }

    public function render()
    {
        $selected = $this->selectedId
            ? Conversation::with([
                'customer.delinquencyStatus', 'customer.articles', 'assignee:id,name', 'chatbotSession',
                'assignments' => fn ($q) => $q->with(['user:id,name', 'performer:id,name'])->latest('id')->limit(8),
            ])->find($this->selectedId)
            : null;

        $counts = Conversation::query()
            ->selectRaw("SUM(CASE WHEN status != 'closed' THEN 1 ELSE 0 END) as open")
            ->selectRaw("SUM(CASE WHEN status = 'pending_agent' THEN 1 ELSE 0 END) as pending")
            ->selectRaw('SUM(CASE WHEN assigned_to = ? AND status != \'closed\' THEN 1 ELSE 0 END) as mine', [auth()->id()])
            ->selectRaw("SUM(CASE WHEN status IN ('assigned','in_progress') THEN 1 ELSE 0 END) as assigned")
            ->selectRaw("SUM(CASE WHEN status = 'bot' THEN 1 ELSE 0 END) as bot")
            ->selectRaw("SUM(CASE WHEN status = 'closed' THEN 1 ELSE 0 END) as closed")
            ->toBase()->first();

        return view('livewire.conversations.inbox', [
            'conversations' => $this->listQuery()
                ->with(['customer:id,full_name,code', 'assignee:id,name'])
                ->orderByRaw("CASE WHEN status = 'pending_agent' THEN 0 ELSE 1 END")
                ->latest('last_message_at')
                ->limit($this->limit)
                ->get(),
            'counts' => $counts,
            'selected' => $selected,
            'entries' => $selected
                ? $selected->entries()->with(['message:id,status,error_message', 'user:id,name'])->latest('id')->limit(150)->get()->reverse()->values()
                : collect(),
            'agents' => $this->canAssign() ? User::active()->permission(Permissions::CONVERSATIONS_ATTEND)->orderBy('name')->get(['id', 'name']) : collect(),
            'canAssign' => $this->canAssign(),
        ]);
    }
}
