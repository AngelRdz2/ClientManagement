<?php

namespace App\Enums;

enum CampaignStatus: string
{
    use EnumHelpers;

    case Draft = 'draft';
    case Scheduled = 'scheduled';
    case Processing = 'processing';
    case Completed = 'completed';
    case Cancelled = 'cancelled';
    case Failed = 'failed';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Borrador',
            self::Scheduled => 'Programada',
            self::Processing => 'En proceso',
            self::Completed => 'Finalizada',
            self::Cancelled => 'Cancelada',
            self::Failed => 'Fallida',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Draft => 'slate',
            self::Scheduled => 'blue',
            self::Processing => 'amber',
            self::Completed => 'green',
            self::Cancelled => 'slate',
            self::Failed => 'red',
        };
    }
}
