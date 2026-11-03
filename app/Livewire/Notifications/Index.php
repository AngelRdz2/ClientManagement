<?php

namespace App\Livewire\Notifications;

use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

#[Title('Notificaciones')]
class Index extends Component
{
    use WithPagination;

    #[Url]
    public string $filter = 'all';

    public function updatedFilter(): void
    {
        $this->resetPage();
    }

    public function open(string $id): void
    {
        $notification = auth()->user()->notifications()->findOrFail($id);
        $notification->markAsRead();

        if ($url = $notification->data['url'] ?? null) {
            $this->redirect($url, navigate: true);
        }
    }

    public function markAllAsRead(): void
    {
        auth()->user()->unreadNotifications->markAsRead();
        $this->dispatch('toast', type: 'success', message: 'Todas las notificaciones fueron marcadas como leídas');
    }

    public function delete(string $id): void
    {
        auth()->user()->notifications()->whereKey($id)->delete();
    }

    public function render()
    {
        $query = auth()->user()->notifications()->latest();
        if ($this->filter === 'unread') {
            $query->whereNull('read_at');
        }

        return view('livewire.notifications.index', [
            'notifications' => $query->paginate(15),
            'unread' => auth()->user()->unreadNotifications()->count(),
        ]);
    }
}
