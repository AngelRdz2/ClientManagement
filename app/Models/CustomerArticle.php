<?php

namespace App\Models;

use App\Enums\ArticleStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'customer_id', 'article_code', 'description', 'contract_number', 'acquired_at', 'original_price',
    'down_payment', 'installments_count', 'installment_amount', 'installments_paid', 'installments_pending',
    'balance', 'next_payment_date', 'days_overdue', 'status',
])]
class CustomerArticle extends Model
{
    use HasFactory;

    protected static function booted(): void
    {
        static::saving(function (CustomerArticle $article) {
            // Las cuotas pendientes se derivan siempre de total - pagadas, para evitar inconsistencias.
            $article->installments_pending = max(0, (int) $article->installments_count - (int) $article->installments_paid);
            if ((float) $article->balance <= 0 && $article->status === ArticleStatus::Active) {
                $article->status = ArticleStatus::PaidOff;
            }
        });

        static::saved(fn (CustomerArticle $article) => $article->customer?->refreshFinancialSummary());
        static::deleted(fn (CustomerArticle $article) => $article->customer?->refreshFinancialSummary());
    }

    protected function casts(): array
    {
        return [
            'acquired_at' => 'date',
            'next_payment_date' => 'date',
            'original_price' => 'decimal:2',
            'down_payment' => 'decimal:2',
            'installment_amount' => 'decimal:2',
            'balance' => 'decimal:2',
            'installments_count' => 'integer',
            'installments_paid' => 'integer',
            'installments_pending' => 'integer',
            'days_overdue' => 'integer',
            'status' => ArticleStatus::class,
        ];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(CustomerPayment::class);
    }

    public function progressPercent(): int
    {
        if ($this->installments_count <= 0) {
            return 0;
        }

        return (int) min(100, round($this->installments_paid / $this->installments_count * 100));
    }
}
