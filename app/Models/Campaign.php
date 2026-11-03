<?php

namespace App\Models;

use App\Enums\CampaignStatus;
use App\Enums\CampaignType;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'name', 'type', 'message_template_id', 'use_status_templates', 'filters', 'status', 'scheduled_at',
    'started_at', 'completed_at', 'total_recipients', 'sent_count', 'failed_count', 'skipped_count', 'created_by',
])]
class Campaign extends Model
{
    protected function casts(): array
    {
        return [
            'type' => CampaignType::class,
            'status' => CampaignStatus::class,
            'filters' => 'array',
            'use_status_templates' => 'boolean',
            'scheduled_at' => 'datetime',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
            'total_recipients' => 'integer',
            'sent_count' => 'integer',
            'failed_count' => 'integer',
            'skipped_count' => 'integer',
        ];
    }

    public function template(): BelongsTo
    {
        return $this->belongsTo(MessageTemplate::class, 'message_template_id');
    }

    public function recipients(): HasMany
    {
        return $this->hasMany(CampaignRecipient::class);
    }

    public function messages(): HasMany
    {
        return $this->hasMany(Message::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function pendingCount(): int
    {
        return max(0, $this->total_recipients - $this->sent_count - $this->failed_count - $this->skipped_count);
    }

    public function progressPercent(): int
    {
        if ($this->total_recipients === 0) {
            return 0;
        }

        return (int) round(($this->total_recipients - $this->pendingCount()) / $this->total_recipients * 100);
    }

    public function isEditable(): bool
    {
        return in_array($this->status, [CampaignStatus::Draft, CampaignStatus::Scheduled], true);
    }
}
