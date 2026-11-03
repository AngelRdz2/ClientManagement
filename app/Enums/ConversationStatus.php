<?php

namespace App\Enums;

enum ConversationStatus: string
{
    use EnumHelpers;

    case Bot = 'bot';
    case PendingAgent = 'pending_agent';
    case Assigned = 'assigned';
    case InProgress = 'in_progress';
    case Closed = 'closed';

    public function label(): string
    {
        return match ($this) {
            self::Bot => 'Bot atendiendo',
            self::PendingAgent => 'Pendiente de asesor',
            self::Assigned => 'Asignada a asesor',
            self::InProgress => 'En atención',
            self::Closed => 'Cerrada',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Bot => 'violet',
            self::PendingAgent => 'red',
            self::Assigned => 'amber',
            self::InProgress => 'blue',
            self::Closed => 'slate',
        };
    }
}
