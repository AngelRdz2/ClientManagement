<?php

namespace App\Models;

use App\Enums\RecipientStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['campaign_id', 'customer_id', 'message_id', 'message_template_id', 'phone', 'rendered_body', 'status', 'skip_reason', 'error_message', 'processed_at'])]
class CampaignRecipient extends Model
{
    protected function casts(): array
    {
        return ['status' => RecipientStatus::class, 'processed_at' => 'datetime'];
    }

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(Campaign::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function message(): BelongsTo
    {
        return $this->belongsTo(Message::class);
    }
}
