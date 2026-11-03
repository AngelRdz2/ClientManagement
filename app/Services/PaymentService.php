<?php

namespace App\Services;

use App\Enums\ArticleStatus;
use App\Enums\PaymentMethod;
use App\Models\CustomerArticle;
use App\Models\CustomerPayment;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Registro de pagos: reduce el saldo del artículo, recalcula cuotas pagadas y avanza la próxima fecha de pago.
 */
class PaymentService
{
    public function __construct(private DelinquencyService $delinquency) {}

    public function register(
        CustomerArticle $article,
        float $amount,
        string $paidAt,
        PaymentMethod $method,
        ?string $reference = null,
        ?string $notes = null,
        ?User $user = null,
    ): CustomerPayment {
        if ($amount <= 0) {
            throw new InvalidArgumentException('El monto debe ser mayor a cero.');
        }
        if ($amount > (float) $article->balance + 0.001) {
            throw new InvalidArgumentException('El monto excede el saldo pendiente del artículo ('.money($article->balance).').');
        }

        return DB::transaction(function () use ($article, $amount, $paidAt, $method, $reference, $notes, $user) {
            $article = CustomerArticle::lockForUpdate()->findOrFail($article->id);

            $previousPaid = $article->installments_paid;
            $newBalance = round((float) $article->balance - $amount, 2);
            $installment = (float) $article->installment_amount;

            // Cuotas pagadas = total de cuotas - cuotas que aún cubre el saldo restante.
            $paidInstallments = $installment > 0
                ? max($previousPaid, $article->installments_count - (int) ceil(round($newBalance / $installment, 4)))
                : $previousPaid;
            $covered = max(0, $paidInstallments - $previousPaid);

            $nextDate = $article->next_payment_date
                ? CarbonImmutable::parse($article->next_payment_date)->addMonthsNoOverflow($covered)
                : null;

            $article->fill([
                'balance' => max(0, $newBalance),
                'installments_paid' => min($paidInstallments, $article->installments_count),
                'next_payment_date' => $newBalance > 0 ? $nextDate : null,
            ]);
            if ($newBalance <= 0) {
                $article->status = ArticleStatus::PaidOff;
            }
            $article->save();

            $payment = CustomerPayment::create([
                'customer_id' => $article->customer_id,
                'customer_article_id' => $article->id,
                'amount' => $amount,
                'installments_covered' => $covered,
                'paid_at' => $paidAt,
                'method' => $method,
                'reference' => $reference,
                'notes' => $notes,
                'registered_by' => $user?->id,
            ]);

            $customer = $article->customer()->first();
            $this->delinquency->updateArticleDays($customer);
            if (setting('collection.auto_recalculate_enabled')) {
                $this->delinquency->recalculate($customer, $user);
            }

            ActivityLogger::log('customer.payment', 'Registró pago de '.money($amount)." de {$customer->full_name} ({$article->description})", $customer, ['payment_id' => $payment->id], $user?->id);

            return $payment;
        });
    }
}
