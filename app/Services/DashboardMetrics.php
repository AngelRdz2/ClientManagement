<?php

namespace App\Services;

use App\Enums\ArticleStatus;
use App\Enums\ConversationStatus;
use App\Enums\CustomerStatus;
use App\Enums\MessageDirection;
use App\Enums\MessageStatus;
use App\Models\Conversation;
use App\Models\Customer;
use App\Models\CustomerArticle;
use App\Models\CustomerPayment;
use App\Models\DelinquencyStatus;
use App\Models\Message;
use Carbon\CarbonImmutable;
use Carbon\CarbonPeriod;
use Illuminate\Support\Facades\DB;

/**
 * Indicadores del dashboard calculados desde la base de datos.
 */
class DashboardMetrics
{
    /** @return array<string, int|float> */
    public function kpis(): array
    {
        $customers = Customer::query()
            ->where('customers.status', CustomerStatus::Active)
            ->leftJoin('delinquency_statuses as ds', 'ds.id', '=', 'customers.delinquency_status_id')
            ->selectRaw('COUNT(*) as total')
            ->selectRaw('SUM(CASE WHEN ds.is_solvent = 1 THEN 1 ELSE 0 END) as solvent')
            ->selectRaw('SUM(CASE WHEN ds.id IS NOT NULL AND ds.is_solvent = 0 THEN 1 ELSE 0 END) as delinquent')
            ->selectRaw('SUM(CASE WHEN ds.is_critical = 1 THEN 1 ELSE 0 END) as critical')
            ->selectRaw('COALESCE(SUM(customers.outstanding_balance), 0) as balance')
            ->toBase()
            ->first();

        $monthStart = now()->startOfMonth();
        $messages = Message::query()
            ->where('direction', MessageDirection::Outbound)
            ->selectRaw('SUM(CASE WHEN status IN (?, ?, ?) AND created_at >= ? THEN 1 ELSE 0 END) as sent_month', [
                MessageStatus::Sent->value, MessageStatus::Delivered->value, MessageStatus::Read->value, $monthStart,
            ])
            ->selectRaw('SUM(CASE WHEN status IN (?, ?) THEN 1 ELSE 0 END) as pending', [MessageStatus::Pending->value, MessageStatus::Queued->value])
            ->selectRaw('SUM(CASE WHEN status = ? AND created_at >= ? THEN 1 ELSE 0 END) as failed_month', [MessageStatus::Failed->value, $monthStart])
            ->toBase()
            ->first();

        return [
            'customers_total' => (int) $customers->total,
            'customers_solvent' => (int) $customers->solvent,
            'customers_delinquent' => (int) $customers->delinquent,
            'customers_critical' => (int) $customers->critical,
            'outstanding_balance' => (float) $customers->balance,
            'pending_accounts' => CustomerArticle::query()
                ->whereIn('status', [ArticleStatus::Active, ArticleStatus::InCollection])
                ->where('balance', '>', 0)
                ->count(),
            'messages_sent_month' => (int) $messages->sent_month,
            'messages_pending' => (int) $messages->pending,
            'messages_failed_month' => (int) $messages->failed_month,
            'conversations_active' => Conversation::query()->open()->count(),
            'agent_requests' => Conversation::query()->where('status', ConversationStatus::PendingAgent)->count(),
        ];
    }

    /** Distribución de clientes activos por estado de mora. */
    public function delinquencyDistribution(): array
    {
        $statuses = DelinquencyStatus::query()
            ->ordered()
            ->withCount(['customers' => fn ($q) => $q->where('status', CustomerStatus::Active)])
            ->withSum(['customers' => fn ($q) => $q->where('status', CustomerStatus::Active)], 'outstanding_balance')
            ->get()
            ->filter(fn ($s) => $s->customers_count > 0 || $s->is_active)
            ->values();

        $withoutStatus = Customer::query()->active()->whereNull('delinquency_status_id')->count();

        $labels = $statuses->pluck('name')->all();
        $counts = $statuses->pluck('customers_count')->all();
        $balances = $statuses->map(fn ($s) => round((float) $s->customers_sum_outstanding_balance, 2))->all();
        $colors = $statuses->pluck('color')->all();

        if ($withoutStatus > 0) {
            $labels[] = 'Sin estado';
            $counts[] = $withoutStatus;
            $balances[] = 0;
            $colors[] = '#cbd5e1';
        }

        return [
            'labels' => $labels,
            'counts' => $counts,
            'balances' => $balances,
            'colors' => $colors,
            'rows' => $statuses,
        ];
    }

    /** Pagos registrados por mes (últimos N meses). */
    public function collectionTrend(int $months = 6): array
    {
        $start = CarbonImmutable::now()->startOfMonth()->subMonths($months - 1);

        $daily = CustomerPayment::query()
            ->where('paid_at', '>=', $start->toDateString())
            ->selectRaw('DATE(paid_at) as day, SUM(amount) as total, COUNT(*) as payments')
            ->groupBy(DB::raw('DATE(paid_at)'))
            ->get();

        $buckets = [];
        foreach (CarbonPeriod::create($start, '1 month', CarbonImmutable::now()->startOfMonth()) as $month) {
            $buckets[$month->format('Y-m')] = ['label' => ucfirst($month->locale('es')->translatedFormat('M Y')), 'total' => 0.0, 'payments' => 0];
        }

        foreach ($daily as $row) {
            $key = substr((string) $row->day, 0, 7);
            if (isset($buckets[$key])) {
                $buckets[$key]['total'] += (float) $row->total;
                $buckets[$key]['payments'] += (int) $row->payments;
            }
        }

        return [
            'labels' => array_column($buckets, 'label'),
            'totals' => array_map(fn ($v) => round($v, 2), array_column($buckets, 'total')),
            'payments' => array_column($buckets, 'payments'),
        ];
    }

    /** Mensajes salientes por día (últimos N días), separados por resultado. */
    public function messagesByDay(int $days = 14): array
    {
        $start = CarbonImmutable::now()->startOfDay()->subDays($days - 1);

        $rows = Message::query()
            ->where('direction', MessageDirection::Outbound)
            ->where('created_at', '>=', $start)
            ->selectRaw('DATE(created_at) as day, status, COUNT(*) as total')
            ->groupBy(DB::raw('DATE(created_at)'), 'status')
            ->get();

        $series = [];
        foreach (CarbonPeriod::create($start, '1 day', CarbonImmutable::now()->startOfDay()) as $day) {
            $series[$day->toDateString()] = ['label' => $day->format('d/m'), 'sent' => 0, 'failed' => 0, 'pending' => 0];
        }

        foreach ($rows as $row) {
            $key = (string) $row->day;
            if (! isset($series[$key])) {
                continue;
            }
            $status = $row->status instanceof MessageStatus ? $row->status : MessageStatus::from($row->status);
            $bucket = match ($status) {
                MessageStatus::Failed => 'failed',
                MessageStatus::Pending, MessageStatus::Queued => 'pending',
                default => 'sent',
            };
            $series[$key][$bucket] += (int) $row->total;
        }

        return [
            'labels' => array_column($series, 'label'),
            'sent' => array_column($series, 'sent'),
            'failed' => array_column($series, 'failed'),
            'pending' => array_column($series, 'pending'),
        ];
    }

    /** Clientes por estado del cliente y por consentimiento de comunicación. */
    public function customerBreakdown(): array
    {
        $row = Customer::query()
            ->selectRaw('SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) as active', [CustomerStatus::Active->value])
            ->selectRaw('SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) as inactive', [CustomerStatus::Inactive->value])
            ->selectRaw('SUM(CASE WHEN accepts_promotions = 1 AND whatsapp_opted_out_at IS NULL THEN 1 ELSE 0 END) as promo_consent')
            ->selectRaw('SUM(CASE WHEN whatsapp_opted_out_at IS NOT NULL THEN 1 ELSE 0 END) as opted_out')
            ->toBase()
            ->first();

        return [
            'active' => (int) $row->active,
            'inactive' => (int) $row->inactive,
            'promo_consent' => (int) $row->promo_consent,
            'opted_out' => (int) $row->opted_out,
        ];
    }
}
