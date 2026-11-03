<?php

namespace App\Livewire\Messages;

use App\Enums\MessageDirection;
use App\Enums\MessageStatus;
use App\Models\Campaign;
use App\Models\Message;
use App\Services\ActivityLogger;
use App\Services\Messaging\MessageSender;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

#[Title('Historial de mensajes')]
class Index extends Component
{
    use WithPagination;

    #[Url(as: 'q', except: '')]
    public string $search = '';

    #[Url(except: '')]
    public string $direction = '';

    #[Url(except: '')]
    public string $status = '';

    #[Url(except: '')]
    public string $sender = '';

    #[Url(except: '')]
    public string $campaign = '';

    #[Url(except: '')]
    public string $from = '';

    #[Url(except: '')]
    public string $to = '';

    public function mount(): void
    {
        $this->authorize('messages.send');
    }

    public function updated(): void
    {
        $this->resetPage();
    }

    public function retry(int $id, MessageSender $sender): void
    {
        $message = Message::where('direction', MessageDirection::Outbound)->where('status', MessageStatus::Failed)->findOrFail($id);
        $message->forceFill(['status' => MessageStatus::Pending, 'error_code' => null, 'error_message' => null, 'failed_at' => null, 'attempts' => 0])->save();
        $sender->enqueue($message);
        ActivityLogger::log('message.retry', "Reintentó el envío del mensaje #{$message->id} a +{$message->phone}", $message);
        $this->dispatch('toast', type: 'success', message: 'Mensaje enviado nuevamente a la cola');
    }

    protected function query(): Builder
    {
        return Message::query()
            ->when($this->search !== '', function (Builder $q) {
                $digits = preg_replace('/\D/', '', $this->search);
                $q->where(fn (Builder $w) => $w->whereHas('customer', fn ($c) => $c->search($this->search))
                    ->when(strlen($digits) >= 4, fn ($w2) => $w2->orWhere('phone', 'like', "%{$digits}%"))
                    ->orWhere('body', 'like', "%{$this->search}%"));
            })
            ->when($this->direction !== '', fn (Builder $q) => $q->where('direction', $this->direction))
            ->when($this->status !== '', fn (Builder $q) => $q->where('status', $this->status))
            ->when($this->sender !== '', fn (Builder $q) => $q->where('sender_type', $this->sender))
            ->when(is_numeric($this->campaign), fn (Builder $q) => $q->where('campaign_id', (int) $this->campaign))
            ->when($this->from !== '', fn (Builder $q) => $q->where('created_at', '>=', $this->from.' 00:00:00'))
            ->when($this->to !== '', fn (Builder $q) => $q->where('created_at', '<=', $this->to.' 23:59:59'));
    }

    public function render()
    {
        $stats = $this->query()->selectRaw('status, COUNT(*) as total')->groupBy('status')->pluck('total', 'status');

        return view('livewire.messages.index', [
            'messages' => $this->query()->with(['customer:id,code,full_name', 'campaign:id,name,type', 'user:id,name'])->latest('id')->paginate(20),
            'stats' => $stats,
            'campaigns' => Campaign::latest('id')->limit(50)->get(['id', 'name']),
        ]);
    }
}
