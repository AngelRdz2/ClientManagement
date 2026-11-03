<?php

namespace App\Models;

use App\Enums\ConversationStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

#[Fillable([
    'customer_id', 'phone', 'contact_name', 'provider', 'status', 'assigned_to', 'unread_count', 'last_message_at',
    'last_inbound_at', 'last_message_preview', 'agent_requested_at', 'closed_at', 'is_simulated',
])]
class Conversation extends Model
{
    protected function casts(): array
    {
        return [
            'status' => ConversationStatus::class,
            'last_message_at' => 'datetime',
            'last_inbound_at' => 'datetime',
            'agent_requested_at' => 'datetime',
            'closed_at' => 'datetime',
            'is_simulated' => 'boolean',
            'unread_count' => 'integer',
        ];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function entries(): HasMany
    {
        return $this->hasMany(ConversationMessage::class);
    }

    public function messages(): HasMany
    {
        return $this->hasMany(Message::class);
    }

    public function assignments(): HasMany
    {
        return $this->hasMany(ConversationAssignment::class);
    }

    public function chatbotSession(): HasOne
    {
        return $this->hasOne(ChatbotSession::class);
    }

    public function scopeOpen(Builder $query): void
    {
        $query->where('status', '!=', ConversationStatus::Closed);
    }

    public function isBotHandling(): bool
    {
        return $this->status === ConversationStatus::Bot;
    }

    /** Ventana de atención de 24 h de WhatsApp: fuera de ella solo se permiten plantillas aprobadas. */
    public function withinServiceWindow(): bool
    {
        return $this->last_inbound_at !== null && $this->last_inbound_at->gt(now()->subHours(24));
    }

    public function displayName(): string
    {
        return $this->customer?->full_name ?? $this->contact_name ?? '+'.$this->phone;
    }
}
