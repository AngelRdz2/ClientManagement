<?php

namespace App\Enums;

enum TemplateCategory: string
{
    use EnumHelpers;

    case Collection = 'collection';
    case Promotion = 'promotion';
    case Reminder = 'reminder';
    case CustomerService = 'customer_service';
    case Custom = 'custom';

    public function label(): string
    {
        return match ($this) {
            self::Collection => 'Cobranza',
            self::Promotion => 'Promociones',
            self::Reminder => 'Recordatorios',
            self::CustomerService => 'Atención al cliente',
            self::Custom => 'Personalizadas',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Collection => 'red',
            self::Promotion => 'violet',
            self::Reminder => 'amber',
            self::CustomerService => 'blue',
            self::Custom => 'slate',
        };
    }
}
