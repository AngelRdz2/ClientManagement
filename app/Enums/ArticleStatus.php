<?php

namespace App\Enums;

enum ArticleStatus: string
{
    use EnumHelpers;

    case Active = 'active';
    case PaidOff = 'paid_off';
    case Cancelled = 'cancelled';
    case InCollection = 'in_collection';

    public function label(): string
    {
        return match ($this) {
            self::Active => 'Vigente',
            self::PaidOff => 'Cancelado (pagado)',
            self::Cancelled => 'Anulado',
            self::InCollection => 'En cobranza',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Active => 'blue',
            self::PaidOff => 'green',
            self::Cancelled => 'slate',
            self::InCollection => 'red',
        };
    }
}
