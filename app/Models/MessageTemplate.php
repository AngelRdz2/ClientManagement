<?php

namespace App\Models;

use App\Enums\TemplateCategory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['name', 'category', 'description', 'body', 'provider_template_name', 'provider_template_language', 'twilio_content_sid', 'is_active', 'created_by', 'updated_by'])]
class MessageTemplate extends Model
{
    use HasFactory, SoftDeletes;

    protected function casts(): array
    {
        return ['category' => TemplateCategory::class, 'is_active' => 'boolean'];
    }

    public function delinquencyStatuses(): HasMany
    {
        return $this->hasMany(DelinquencyStatus::class);
    }

    public function campaigns(): HasMany
    {
        return $this->hasMany(Campaign::class);
    }

    public function scopeActive(Builder $query): void
    {
        $query->where('is_active', true);
    }

    public function scopeCategory(Builder $query, TemplateCategory|string $category): void
    {
        $query->where('category', $category instanceof TemplateCategory ? $category->value : $category);
    }

    /**
     * Identificador de la plantilla aprobada para un proveedor: nombre en Meta o Content SID (HX...) en Twilio.
     * Null si la plantilla no está aprobada en ese proveedor.
     */
    public function approvedIdFor(string $provider): ?string
    {
        return match ($provider) {
            'meta' => $this->provider_template_name ?: null,
            'twilio' => $this->twilio_content_sid ?: null,
            default => null,
        };
    }
}
