<?php

namespace App\Enums;

enum MessageDirection: string
{
    use EnumHelpers;

    case Outbound = 'outbound';
    case Inbound = 'inbound';

    public function label(): string
    {
        return match ($this) {
            self::Outbound => 'Saliente',
            self::Inbound => 'Entrante',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Outbound => 'indigo',
            self::Inbound => 'blue',
        };
    }
}
