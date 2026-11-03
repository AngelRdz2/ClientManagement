<?php

namespace App\Enums;

enum ChatbotState: string
{
    use EnumHelpers;

    case Idle = 'idle';
    case AwaitingDui = 'awaiting_dui';
    case AwaitingSecondFactor = 'awaiting_second_factor';
    case Locked = 'locked';

    public function label(): string
    {
        return match ($this) {
            self::Idle => 'En espera',
            self::AwaitingDui => 'Esperando DUI',
            self::AwaitingSecondFactor => 'Esperando verificación',
            self::Locked => 'Bloqueado',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Idle => 'slate',
            self::AwaitingDui => 'amber',
            self::AwaitingSecondFactor => 'amber',
            self::Locked => 'red',
        };
    }
}
