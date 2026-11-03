<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['intent', 'name', 'keywords', 'response', 'requires_verification', 'priority', 'is_active'])]
class ChatbotFlow extends Model
{
    protected function casts(): array
    {
        return ['keywords' => 'array', 'requires_verification' => 'boolean', 'is_active' => 'boolean', 'priority' => 'integer'];
    }

    public function scopeActive(Builder $query): void
    {
        $query->where('is_active', true);
    }
}
