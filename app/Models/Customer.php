<?php

namespace App\Models;

use App\Enums\CustomerStatus;
use App\Support\Phone;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

#[Fillable([
    'code', 'full_name', 'dui', 'phone_primary', 'phone_secondary', 'email', 'address', 'registered_at',
    'status', 'delinquency_status_id', 'days_overdue', 'accepts_collection_messages', 'accepts_promotions',
    'promotions_consent_at', 'promotions_consent_source', 'whatsapp_opted_out_at', 'notes',
    'created_by', 'updated_by',
])]
class Customer extends Model
{
    use HasFactory;

    protected static function booted(): void
    {
        static::saving(function (Customer $customer) {
            if ($customer->isDirty('phone_primary')) {
                $customer->whatsapp_number = Phone::normalize($customer->phone_primary);
            }
        });
    }

    protected function casts(): array
    {
        return [
            'registered_at' => 'date',
            'next_payment_date' => 'date',
            'status' => CustomerStatus::class,
            'days_overdue' => 'integer',
            'outstanding_balance' => 'decimal:2',
            'pending_installments' => 'integer',
            'accepts_collection_messages' => 'boolean',
            'accepts_promotions' => 'boolean',
            'promotions_consent_at' => 'datetime',
            'whatsapp_opted_out_at' => 'datetime',
        ];
    }

    public function delinquencyStatus(): BelongsTo
    {
        return $this->belongsTo(DelinquencyStatus::class);
    }

    public function articles(): HasMany
    {
        return $this->hasMany(CustomerArticle::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(CustomerPayment::class);
    }

    public function statusHistories(): HasMany
    {
        return $this->hasMany(CustomerStatusHistory::class);
    }

    public function messages(): HasMany
    {
        return $this->hasMany(Message::class);
    }

    public function conversations(): HasMany
    {
        return $this->hasMany(Conversation::class);
    }

    public function latestConversation(): HasOne
    {
        return $this->hasOne(Conversation::class)->latestOfMany('last_message_at');
    }

    public function groups(): BelongsToMany
    {
        return $this->belongsToMany(CustomerGroup::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function scopeActive(Builder $query): void
    {
        $query->where('status', CustomerStatus::Active);
    }

    public function scopeSearch(Builder $query, ?string $term): void
    {
        $term = trim((string) $term);
        if ($term === '') {
            return;
        }

        $digits = preg_replace('/\D/', '', $term);
        $query->where(function (Builder $q) use ($term, $digits) {
            $q->where('full_name', 'like', "%{$term}%")
                ->orWhere('code', 'like', "{$term}%")
                ->orWhere('dui', 'like', "{$term}%")
                ->orWhere('phone_primary', 'like', "%{$term}%")
                ->orWhere('phone_secondary', 'like', "%{$term}%");
            if (strlen($digits) >= 4) {
                $q->orWhere('whatsapp_number', 'like', "%{$digits}%");
            }
        });
    }

    /** Clientes que pueden recibir mensajes de WhatsApp (no han pedido la baja). */
    public function scopeReachable(Builder $query): void
    {
        $query->active()->whereNull('whatsapp_opted_out_at')->whereNotNull('whatsapp_number');
    }

    public function isActive(): bool
    {
        return $this->status === CustomerStatus::Active;
    }

    public function isSolvent(): bool
    {
        return (bool) $this->delinquencyStatus?->is_solvent;
    }

    public function canReceivePromotions(): bool
    {
        return $this->isActive() && $this->accepts_promotions && $this->whatsapp_opted_out_at === null;
    }

    public function canReceiveCollectionMessages(): bool
    {
        return $this->isActive()
            && $this->accepts_collection_messages
            && $this->whatsapp_opted_out_at === null
            && $this->delinquencyStatus !== null
            && $this->delinquencyStatus->receivesCollectionMessages();
    }

    /** Recalcula los totales desnormalizados a partir de los artículos. */
    public function refreshFinancialSummary(): void
    {
        $totals = $this->articles()
            ->whereIn('status', ['active', 'in_collection'])
            ->selectRaw('COALESCE(SUM(balance),0) as balance, COALESCE(SUM(installments_pending),0) as pending, MIN(next_payment_date) as next_date, COALESCE(MAX(days_overdue),0) as max_days')
            ->first();

        $this->forceFill([
            'outstanding_balance' => $totals->balance,
            'pending_installments' => (int) $totals->pending,
            'next_payment_date' => $totals->next_date,
        ])->saveQuietly();
    }
}
