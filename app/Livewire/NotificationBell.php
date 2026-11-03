<?php

namespace App\Livewire;

use Livewire\Component;

/**
 * Campana de notificaciones internas del encabezado (base de datos, refresco cada 20 s).
 */
class NotificationBell extends Component
{
    public function markAsRead(string $id): void
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
    }

    public function render()
    {
        $user = auth()->user();

        return view('livewire.notification-bell', [
            'unreadCount' => $user->unreadNotifications()->count(),
            'notifications' => $user->notifications()->latest()->limit(8)->get(),
        ]);
    }
}
