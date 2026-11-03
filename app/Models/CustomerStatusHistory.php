<?php

namespace App\Models;

use App\Enums\StatusChangeSource;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

/**
 * Historial inmutable de cambios de estado de mora: solo se insertan registros.
 */
#[Fillable(['customer_id', 'from_status_id', 'to_status_id', 'from_days_overdue', 'to_days_overdue', 'source', 'reason', 'user_id'])]
class CustomerStatusHistory extends Model
{
    public const UPDATED_AT = null;

    protected static function booted(): void
    {
        static::updating(fn () => throw new LogicException('El historial de estados no puede modificarse.'));
        static::deleting(fn () => throw new LogicException('El historial de estados no puede eliminarse.'));
    }

    protected function casts(): array
    {
        return ['source' => StatusChangeSource::class, 'created_at' => 'datetime'];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function fromStatus(): BelongsTo
    {
        return $this->belongsTo(DelinquencyStatus::class, 'from_status_id');
    }

    public function toStatus(): BelongsTo
    {
        return $this->belongsTo(DelinquencyStatus::class, 'to_status_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
