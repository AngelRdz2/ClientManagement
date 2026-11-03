<?php

namespace App\Enums;

enum StatusChangeSource: string
{
    use EnumHelpers;

    case Manual = 'manual';
    case Import = 'import';
    case Automatic = 'automatic';
    case System = 'system';

    public function label(): string
    {
        return match ($this) {
            self::Manual => 'Manual',
            self::Import => 'Importación',
            self::Automatic => 'Automático',
            self::System => 'Sistema',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Manual => 'indigo',
            self::Import => 'blue',
            self::Automatic => 'violet',
            self::System => 'slate',
        };
    }
}
