<?php

namespace App\Enums;

enum PaymentMethod: string
{
    use EnumHelpers;

    case Cash = 'cash';
    case Transfer = 'transfer';
    case Card = 'card';
    case Deposit = 'deposit';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Cash => 'Efectivo',
            self::Transfer => 'Transferencia',
            self::Card => 'Tarjeta',
            self::Deposit => 'Depósito',
            self::Other => 'Otro',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Cash => 'green',
            self::Transfer => 'blue',
            self::Card => 'indigo',
            self::Deposit => 'violet',
            self::Other => 'slate',
        };
    }
}
