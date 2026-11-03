<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['conversation_id', 'user_id', 'performed_by', 'action', 'notes'])]
class ConversationAssignment extends Model
{
    public const UPDATED_AT = null;

    public const ACTION_LABELS = [
        'agent_requested' => 'Cliente solicitó asesor',
        'taken' => 'Tomada por asesor',
        'assigned' => 'Asignada',
        'returned_to_bot' => 'Devuelta al bot',
        'closed' => 'Cerrada',
        'reopened' => 'Reabierta',
    ];

    protected function casts(): array
    {
        return ['created_at' => 'datetime'];
    }

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function performer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'performed_by');
    }

    public function actionLabel(): string
    {
        return self::ACTION_LABELS[$this->action] ?? $this->action;
    }
}
