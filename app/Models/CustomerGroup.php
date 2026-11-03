<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

#[Fillable(['name', 'description', 'color'])]
class CustomerGroup extends Model
{
    public function customers(): BelongsToMany
    {
        return $this->belongsToMany(Customer::class);
    }
}
