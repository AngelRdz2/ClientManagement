<?php

namespace App\Enums;

enum MessageStatus: string
{
    use EnumHelpers;

    case Pending = 'pending';
    case Queued = 'queued';
    case Sent = 'sent';
    case Delivered = 'delivered';
    case Read = 'read';
    case Failed = 'failed';
    case Received = 'received';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Pendiente',
            self::Queued => 'En cola',
            self::Sent => 'Enviado',
            self::Delivered => 'Entregado',
            self::Read => 'Leído',
            self::Failed => 'Fallido',
            self::Received => 'Recibido',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Pending => 'slate',
            self::Queued => 'blue',
            self::Sent => 'indigo',
            self::Delivered => 'green',
            self::Read => 'green',
            self::Failed => 'red',
            self::Received => 'blue',
        };
    }
}
