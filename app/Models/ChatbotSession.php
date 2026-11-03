<?php

namespace App\Models;

use App\Enums\ChatbotState;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'conversation_id', 'customer_id', 'state', 'pending_intent', 'context', 'failed_attempts', 'verified_at',
    'verification_expires_at', 'locked_until', 'last_interaction_at',
])]
class ChatbotSession extends Model
{
    protected function casts(): array
    {
        return [
            'state' => ChatbotState::class,
            'context' => 'array',
            'failed_attempts' => 'integer',
            'verified_at' => 'datetime',
            'verification_expires_at' => 'datetime',
            'locked_until' => 'datetime',
            'last_interaction_at' => 'datetime',
        ];
    }

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function isVerified(): bool
    {
        return $this->customer_id !== null
            && $this->verified_at !== null
            && $this->verification_expires_at?->isFuture();
    }

    public function isLocked(): bool
    {
        return $this->locked_until?->isFuture() ?? false;
    }
}
