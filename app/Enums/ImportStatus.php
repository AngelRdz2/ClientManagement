<?php

namespace App\Enums;

enum ImportStatus: string
{
    use EnumHelpers;

    case Uploaded = 'uploaded';
    case Validated = 'validated';
    case Queued = 'queued';
    case Processing = 'processing';
    case Completed = 'completed';
    case Failed = 'failed';

    public function label(): string
    {
        return match ($this) {
            self::Uploaded => 'Cargado',
            self::Validated => 'Validado',
            self::Queued => 'En cola',
            self::Processing => 'Procesando',
            self::Completed => 'Completado',
            self::Failed => 'Fallido',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Uploaded => 'slate',
            self::Validated => 'blue',
            self::Queued => 'indigo',
            self::Processing => 'amber',
            self::Completed => 'green',
            self::Failed => 'red',
        };
    }
}
