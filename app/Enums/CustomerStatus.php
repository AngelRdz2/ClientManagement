<?php

namespace App\Enums;

enum CustomerStatus: string
{
    use EnumHelpers;

    case Active = 'active';
    case Inactive = 'inactive';

    public function label(): string
    {
        return match ($this) {
            self::Active => 'Activo',
            self::Inactive => 'Inactivo',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Active => 'green',
            self::Inactive => 'slate',
        };
    }
}
