<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['import_batch_id', 'row_number', 'errors', 'row_data'])]
class ImportError extends Model
{
    public const UPDATED_AT = null;

    protected function casts(): array
    {
        return ['errors' => 'array', 'row_data' => 'array', 'created_at' => 'datetime'];
    }

    public function batch(): BelongsTo
    {
        return $this->belongsTo(ImportBatch::class, 'import_batch_id');
    }
}
