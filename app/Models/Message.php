<?php

namespace App\Models;

use App\Enums\MessageDirection;
use App\Enums\MessageStatus;
use App\Enums\SenderType;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Registro de cada mensaje de WhatsApp enviado o recibido a través de un proveedor.
 */
#[Fillable([
    'customer_id', 'conversation_id', 'campaign_id', 'message_template_id', 'direction', 'sender_type', 'user_id',
    'phone', 'body', 'provider', 'provider_message_id', 'idempotency_key', 'status', 'error_code', 'error_message',
    'attempts', 'sent_at', 'delivered_at', 'read_at', 'failed_at', 'meta',
])]
class Message extends Model
{
    protected function casts(): array
    {
        return [
            'direction' => MessageDirection::class,
            'status' => MessageStatus::class,
            'sender_type' => SenderType::class,
            'meta' => 'array',
            'sent_at' => 'datetime',
            'delivered_at' => 'datetime',
            'read_at' => 'datetime',
            'failed_at' => 'datetime',
        ];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class);
    }

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(Campaign::class);
    }

    public function template(): BelongsTo
    {
        return $this->belongsTo(MessageTemplate::class, 'message_template_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
