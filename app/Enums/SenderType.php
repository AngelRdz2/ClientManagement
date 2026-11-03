<?php

namespace App\Enums;

enum SenderType: string
{
    use EnumHelpers;

    case Customer = 'customer';
    case Bot = 'bot';
    case Agent = 'agent';
    case Campaign = 'campaign';
    case System = 'system';

    public function label(): string
    {
        return match ($this) {
            self::Customer => 'Cliente',
            self::Bot => 'Bot',
            self::Agent => 'Asesor',
            self::Campaign => 'Campaña',
            self::System => 'Sistema',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Customer => 'blue',
            self::Bot => 'violet',
            self::Agent => 'indigo',
            self::Campaign => 'amber',
            self::System => 'slate',
        };
    }
}
