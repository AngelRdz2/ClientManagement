<?php

namespace App\Models;

use App\Enums\ImportStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'user_id', 'original_filename', 'stored_path', 'status', 'total_rows', 'valid_rows', 'error_rows', 'duplicate_rows',
    'customers_created', 'customers_updated', 'articles_upserted', 'statuses_changed', 'processed_rows',
    'failure_message', 'started_at', 'finished_at',
])]
class ImportBatch extends Model
{
    protected function casts(): array
    {
        return ['status' => ImportStatus::class, 'started_at' => 'datetime', 'finished_at' => 'datetime'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function importErrors(): HasMany
    {
        return $this->hasMany(ImportError::class)->orderBy('row_number');
    }
}
