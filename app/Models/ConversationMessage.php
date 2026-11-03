<?php

namespace App\Models;

use App\Enums\SenderType;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Línea de tiempo de una conversación: mensajes, eventos del sistema y notas internas.
 */
#[Fillable(['conversation_id', 'message_id', 'type', 'sender_type', 'user_id', 'body'])]
class ConversationMessage extends Model
{
    public const UPDATED_AT = null;

    protected function casts(): array
    {
        return ['sender_type' => SenderType::class, 'created_at' => 'datetime'];
    }

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class);
    }

    public function message(): BelongsTo
    {
        return $this->belongsTo(Message::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isEvent(): bool
    {
        return $this->type === 'event';
    }

    public function isNote(): bool
    {
        return $this->type === 'note';
    }
}
