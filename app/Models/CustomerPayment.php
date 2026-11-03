<?php

namespace App\Models;

use App\Enums\PaymentMethod;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['customer_id', 'customer_article_id', 'amount', 'installments_covered', 'paid_at', 'method', 'reference', 'notes', 'registered_by'])]
class CustomerPayment extends Model
{
    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'installments_covered' => 'integer',
            'paid_at' => 'date',
            'method' => PaymentMethod::class,
        ];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function article(): BelongsTo
    {
        return $this->belongsTo(CustomerArticle::class, 'customer_article_id');
    }

    public function registeredBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'registered_by');
    }
}
