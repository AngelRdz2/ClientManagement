<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Estado de mora administrable desde el sistema (no está definido en código).
 */
#[Fillable([
    'name', 'code', 'description', 'min_days_overdue', 'color', 'priority', 'message_template_id',
    'allows_automatic_messages', 'exclude_from_campaigns', 'is_solvent', 'is_critical', 'is_auto_assignable', 'is_active',
])]
class DelinquencyStatus extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'min_days_overdue' => 'integer',
            'priority' => 'integer',
            'allows_automatic_messages' => 'boolean',
            'exclude_from_campaigns' => 'boolean',
            'is_solvent' => 'boolean',
            'is_critical' => 'boolean',
            'is_auto_assignable' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    public function messageTemplate(): BelongsTo
    {
        return $this->belongsTo(MessageTemplate::class);
    }

    public function customers(): HasMany
    {
        return $this->hasMany(Customer::class);
    }

    public function scopeActive(Builder $query): void
    {
        $query->where('is_active', true);
    }

    public function scopeOrdered(Builder $query): void
    {
        $query->orderBy('priority')->orderBy('min_days_overdue');
    }

    /** Un estado recibe cobranza solo si no es solvente y no está excluido. */
    public function receivesCollectionMessages(): bool
    {
        return ! $this->is_solvent && ! $this->exclude_from_campaigns;
    }
}
