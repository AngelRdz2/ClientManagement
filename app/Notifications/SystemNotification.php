<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

/**
 * Notificación interna (canal base de datos) mostrada en la campana del encabezado y en el centro de notificaciones.
 * Nunca incluye saldos ni datos financieros: solo referencias y un enlace al módulo correspondiente.
 */
class SystemNotification extends Notification
{
    use Queueable;

    public function __construct(
        public string $type,
        public string $title,
        public string $body,
        public string $icon = 'bell',
        public string $color = 'indigo',
        public ?string $url = null,
    ) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function databaseType(object $notifiable): string
    {
        return $this->type;
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type' => $this->type,
            'title' => $this->title,
            'body' => $this->body,
            'icon' => $this->icon,
            'color' => $this->color,
            'url' => $this->url,
        ];
    }
}
