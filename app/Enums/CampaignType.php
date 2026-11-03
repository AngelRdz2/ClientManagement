<?php

namespace App\Enums;

enum CampaignType: string
{
    use EnumHelpers;

    case Collection = 'collection';
    case Promotion = 'promotion';
    case Reminder = 'reminder';
    case Custom = 'custom';

    public function label(): string
    {
        return match ($this) {
            self::Collection => 'Cobranza',
            self::Promotion => 'Promocional',
            self::Reminder => 'Recordatorio',
            self::Custom => 'Personalizada',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Collection => 'red',
            self::Promotion => 'violet',
            self::Reminder => 'amber',
            self::Custom => 'slate',
        };
    }
}
