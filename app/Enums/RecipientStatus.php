<?php

namespace App\Enums;

enum RecipientStatus: string
{
    use EnumHelpers;

    case Pending = 'pending';
    case Queued = 'queued';
    case Sent = 'sent';
    case Failed = 'failed';
    case Skipped = 'skipped';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Pendiente',
            self::Queued => 'En cola',
            self::Sent => 'Enviado',
            self::Failed => 'Fallido',
            self::Skipped => 'Omitido',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Pending => 'slate',
            self::Queued => 'blue',
            self::Sent => 'green',
            self::Failed => 'red',
            self::Skipped => 'amber',
        };
    }
}
