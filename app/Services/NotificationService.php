<?php

namespace App\Services;

use App\Models\User;
use App\Notifications\SystemNotification;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Notification;

/**
 * Envía notificaciones internas respetando la configuración (cada tipo puede desactivarse).
 */
class NotificationService
{
    /** Tipo => clave de configuración que lo habilita */
    public const TYPES = [
        'agent_requested' => 'notifications.agent_requested',
        'new_conversation' => 'notifications.new_conversation',
        'campaign_finished' => 'notifications.campaign_finished',
        'import_failed' => 'notifications.import_failed',
        'import_completed' => null,
        'messages_failed' => 'notifications.messages_failed',
        'conversation_assigned' => 'notifications.conversation_assigned',
    ];

    public function toPermission(string $permission, SystemNotification $notification, ?int $exceptUserId = null): void
    {
        if (! $this->enabled($notification->type)) {
            return;
        }

        $users = User::query()->active()->permission($permission)->when($exceptUserId, fn ($q) => $q->whereKeyNot($exceptUserId))->get();
        $this->send($users, $notification);
    }

    public function toUser(?User $user, SystemNotification $notification): void
    {
        if ($user && $user->is_active && $this->enabled($notification->type)) {
            $user->notify($notification);
        }
    }

    public function enabled(string $type): bool
    {
        $key = self::TYPES[$type] ?? null;

        return $key === null || (bool) setting($key, true);
    }

    private function send(Collection $users, SystemNotification $notification): void
    {
        if ($users->isNotEmpty()) {
            Notification::send($users, $notification);
        }
    }
}
