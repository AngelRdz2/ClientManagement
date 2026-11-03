<?php

namespace App\Services\Reports;

use App\Enums\ConversationStatus;
use App\Enums\CustomerStatus;
use App\Enums\MessageDirection;
use App\Enums\MessageStatus;
use App\Models\Campaign;
use App\Models\ConversationAssignment;
use App\Models\Customer;
use App\Models\CustomerStatusHistory;
use App\Models\DelinquencyStatus;
use App\Models\Message;
use App\Models\User;
use App\Support\Permissions;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Enumerable;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Definición y ejecución de reportes. Cada reporte devuelve encabezados y filas listas para mostrar o exportar.
 */
class ReportService
{
    public const REPORTS = [
        'customers_by_status' => ['Clientes y deuda por estado de mora', 'gauge', false],
        'solvent_customers' => ['Clientes solventes', 'circle-check', false],
        'delinquent_customers' => ['Clientes en mora', 'clock', false],
        'status_history' => ['Historial de cambios de mora', 'history', true],
        'campaigns' => ['Historial de campañas', 'megaphone', true],
        'messages_sent' => ['Mensajes enviados', 'send', true],
        'messages_received' => ['Mensajes recibidos', 'inbox', true],
        'messages_failed' => ['Mensajes fallidos', 'circle-x', true],
        'agent_conversations' => ['Conversaciones atendidas por asesor', 'headset', true],
        'agent_requests' => ['Solicitudes de asesor', 'bell-ring', true],
    ];

    /** @return array{title:string, headings: list<string>, rows: iterable, total:int, summary: array<string, string>} */
    public function run(string $report, ?string $from, ?string $to, ?int $limit = null): array
    {
        if (! isset(self::REPORTS[$report])) {
            throw new InvalidArgumentException('Reporte desconocido');
        }

        $start = $from ? CarbonImmutable::parse($from)->startOfDay() : now()->startOfMonth()->toImmutable();
        $end = $to ? CarbonImmutable::parse($to)->endOfDay() : now()->endOfDay()->toImmutable();

        $result = $this->{'report'.str_replace('_', '', ucwords($report, '_'))}($start, $end, $limit);

        return ['title' => self::REPORTS[$report][0]] + $result;
    }

    /** Vista previa: primeras filas. Exportación: lectura perezosa por bloques. */
    private function rows(Builder $query, ?int $limit): Enumerable
    {
        return $limit ? $query->limit($limit)->get() : $query->lazy(500);
    }

    private function reportCustomersByStatus(CarbonImmutable $start, CarbonImmutable $end, ?int $limit): array
    {
        $rows = DelinquencyStatus::query()->ordered()
            ->withCount(['customers as active_count' => fn ($q) => $q->where('status', CustomerStatus::Active)])
            ->withSum(['customers as balance' => fn ($q) => $q->where('status', CustomerStatus::Active)], 'outstanding_balance')
            ->withSum(['customers as installments' => fn ($q) => $q->where('status', CustomerStatus::Active)], 'pending_installments')
            ->get()
            ->map(fn ($s) => [$s->name, $s->min_days_overdue, $s->is_solvent ? 'Sí' : 'No', $s->is_critical ? 'Sí' : 'No', (int) $s->active_count, (int) $s->installments, round((float) $s->balance, 2)]);

        $total = $rows->sum(fn ($r) => $r[6]);

        return [
            'headings' => ['Estado de mora', 'Días desde', 'Solvente', 'Crítico', 'Clientes activos', 'Cuotas pendientes', 'Saldo pendiente ($)'],
            'rows' => $rows->all(),
            'total' => $rows->count(),
            'summary' => ['Clientes' => number_format($rows->sum(fn ($r) => $r[4])), 'Deuda total' => money($total)],
            'money_columns' => [6],
        ];
    }

    private function customerList(bool $solvent, ?int $limit): array
    {
        $query = Customer::query()->active()
            ->whereHas('delinquencyStatus', fn ($q) => $q->where('is_solvent', $solvent))
            ->with('delinquencyStatus:id,name')
            ->orderByDesc('days_overdue')->orderBy('full_name');

        $total = (clone $query)->count();
        $sum = (clone $query)->sum('outstanding_balance');

        return [
            'headings' => ['Código', 'Nombre', 'DUI', 'Teléfono', 'Estado de mora', 'Días de mora', 'Cuotas pendientes', 'Próximo pago', 'Saldo ($)'],
            'rows' => $this->rows($query, $limit)->map(fn (Customer $c) => [
                $c->code, $c->full_name, $c->dui, $c->phone_primary, $c->delinquencyStatus?->name, $c->days_overdue,
                $c->pending_installments, $c->next_payment_date?->format('d/m/Y'), (float) $c->outstanding_balance,
            ]),
            'total' => $total,
            'summary' => ['Clientes' => number_format($total), 'Saldo' => money($sum)],
            'money_columns' => [8],
        ];
    }

    private function reportSolventCustomers(CarbonImmutable $start, CarbonImmutable $end, ?int $limit): array
    {
        return $this->customerList(true, $limit);
    }

    private function reportDelinquentCustomers(CarbonImmutable $start, CarbonImmutable $end, ?int $limit): array
    {
        return $this->customerList(false, $limit);
    }

    private function reportStatusHistory(CarbonImmutable $start, CarbonImmutable $end, ?int $limit): array
    {
        $query = CustomerStatusHistory::query()->whereBetween('created_at', [$start, $end])
            ->with(['customer:id,code,full_name', 'fromStatus:id,name', 'toStatus:id,name', 'user:id,name'])->latest('id');
        $total = (clone $query)->count();

        return [
            'headings' => ['Fecha', 'Código', 'Cliente', 'Estado anterior', 'Estado nuevo', 'Días antes', 'Días después', 'Origen', 'Motivo', 'Usuario'],
            'rows' => $this->rows($query, $limit)->map(fn ($h) => [
                $h->created_at->format('d/m/Y H:i'), $h->customer?->code, $h->customer?->full_name, $h->fromStatus?->name ?? '—', $h->toStatus?->name ?? '—',
                $h->from_days_overdue, $h->to_days_overdue, $h->source->label(), $h->reason, $h->user?->name ?? 'Sistema',
            ]),
            'total' => $total,
            'summary' => ['Cambios' => number_format($total)],
        ];
    }

    private function reportCampaigns(CarbonImmutable $start, CarbonImmutable $end, ?int $limit): array
    {
        $query = Campaign::query()->whereBetween('created_at', [$start, $end])->with(['template:id,name', 'creator:id,name'])->latest('id');
        $total = (clone $query)->count();
        $agg = (clone $query)->selectRaw('COALESCE(SUM(sent_count),0) sent, COALESCE(SUM(failed_count),0) failed')->toBase()->first();

        return [
            'headings' => ['Fecha', 'Campaña', 'Tipo', 'Estado', 'Plantilla', 'Programada', 'Destinatarios', 'Enviados', 'Fallidos', 'Omitidos', 'Pendientes', 'Creada por'],
            'rows' => $query->when($limit, fn ($q) => $q->limit($limit))->get()->map(fn (Campaign $c) => [
                $c->created_at->format('d/m/Y H:i'), $c->name, $c->type->label(), $c->status->label(),
                $c->use_status_templates ? 'Según estado de mora' : $c->template?->name, $c->scheduled_at?->format('d/m/Y H:i'),
                $c->total_recipients, $c->sent_count, $c->failed_count, $c->skipped_count, $c->pendingCount(), $c->creator?->name,
            ]),
            'total' => $total,
            'summary' => ['Campañas' => number_format($total), 'Enviados' => number_format($agg->sent), 'Fallidos' => number_format($agg->failed)],
        ];
    }

    private function messages(CarbonImmutable $start, CarbonImmutable $end, ?int $limit, callable $scope): array
    {
        $query = Message::query()->whereBetween('created_at', [$start, $end])->tap($scope)
            ->with(['customer:id,code,full_name', 'campaign:id,name', 'user:id,name'])->latest('id');
        $total = (clone $query)->count();

        return [
            'headings' => ['Fecha', 'Código', 'Cliente', 'Teléfono', 'Mensaje', 'Origen', 'Campaña / Usuario', 'Proveedor', 'Estado', 'Error'],
            'rows' => $this->rows($query, $limit)->map(fn (Message $m) => [
                $m->created_at->format('d/m/Y H:i:s'), $m->customer?->code, $m->customer?->full_name, '+'.$m->phone, $m->body,
                $m->sender_type->label(), $m->campaign?->name ?? $m->user?->name, $m->provider === 'simulator' ? 'Simulado' : $m->provider,
                $m->status->label(), $m->error_message,
            ]),
            'total' => $total,
            'summary' => ['Mensajes' => number_format($total)],
        ];
    }

    private function reportMessagesSent(CarbonImmutable $start, CarbonImmutable $end, ?int $limit): array
    {
        return $this->messages($start, $end, $limit, fn ($q) => $q->where('direction', MessageDirection::Outbound)
            ->whereIn('status', [MessageStatus::Sent->value, MessageStatus::Delivered->value, MessageStatus::Read->value]));
    }

    private function reportMessagesReceived(CarbonImmutable $start, CarbonImmutable $end, ?int $limit): array
    {
        return $this->messages($start, $end, $limit, fn ($q) => $q->where('direction', MessageDirection::Inbound));
    }

    private function reportMessagesFailed(CarbonImmutable $start, CarbonImmutable $end, ?int $limit): array
    {
        return $this->messages($start, $end, $limit, fn ($q) => $q->where('direction', MessageDirection::Outbound)->where('status', MessageStatus::Failed));
    }

    private function reportAgentConversations(CarbonImmutable $start, CarbonImmutable $end, ?int $limit): array
    {
        $agents = User::query()->permission(Permissions::CONVERSATIONS_ATTEND)->orderBy('name')->get(['id', 'name', 'email']);

        $taken = ConversationAssignment::query()->whereBetween('created_at', [$start, $end])->whereIn('action', ['taken', 'assigned'])
            ->selectRaw('user_id, COUNT(DISTINCT conversation_id) as total')->groupBy('user_id')->pluck('total', 'user_id');
        $closed = ConversationAssignment::query()->whereBetween('created_at', [$start, $end])->where('action', 'closed')
            ->selectRaw('user_id, COUNT(*) as total')->groupBy('user_id')->pluck('total', 'user_id');
        $returned = ConversationAssignment::query()->whereBetween('created_at', [$start, $end])->where('action', 'returned_to_bot')
            ->selectRaw('performed_by, COUNT(*) as total')->groupBy('performed_by')->pluck('total', 'performed_by');
        $messages = Message::query()->whereBetween('created_at', [$start, $end])->where('sender_type', 'agent')->whereNotNull('user_id')
            ->selectRaw('user_id, COUNT(*) as total')->groupBy('user_id')->pluck('total', 'user_id');
        $open = DB::table('conversations')->whereIn('status', [ConversationStatus::Assigned->value, ConversationStatus::InProgress->value])
            ->selectRaw('assigned_to, COUNT(*) as total')->groupBy('assigned_to')->pluck('total', 'assigned_to');

        $rows = $agents->map(fn ($a) => [
            $a->name, $a->email, (int) ($taken[$a->id] ?? 0), (int) ($messages[$a->id] ?? 0), (int) ($closed[$a->id] ?? 0),
            (int) ($returned[$a->id] ?? 0), (int) ($open[$a->id] ?? 0),
        ]);

        return [
            'headings' => ['Asesor', 'Correo', 'Conversaciones atendidas', 'Mensajes enviados', 'Cerradas', 'Devueltas al bot', 'Abiertas actualmente'],
            'rows' => $rows->all(),
            'total' => $rows->count(),
            'summary' => ['Atendidas' => number_format($rows->sum(fn ($r) => $r[2])), 'Mensajes de asesores' => number_format($rows->sum(fn ($r) => $r[3]))],
        ];
    }

    private function reportAgentRequests(CarbonImmutable $start, CarbonImmutable $end, ?int $limit): array
    {
        $query = ConversationAssignment::query()->where('action', 'agent_requested')->whereBetween('created_at', [$start, $end])
            ->with(['conversation:id,phone,contact_name,customer_id,status', 'conversation.customer:id,code,full_name'])->latest('id');
        $total = (clone $query)->count();

        $requests = $query->when($limit, fn ($q) => $q->limit($limit))->get();
        $firstTaken = ConversationAssignment::query()->whereIn('conversation_id', $requests->pluck('conversation_id'))->whereIn('action', ['taken', 'assigned'])
            ->with('user:id,name')->orderBy('id')->get()->groupBy('conversation_id');

        $waits = [];
        $rows = $requests->map(function ($r) use ($firstTaken, &$waits) {
            $taken = ($firstTaken[$r->conversation_id] ?? collect())->first(fn ($t) => $t->created_at->gte($r->created_at));
            $minutes = $taken ? (int) $r->created_at->diffInMinutes($taken->created_at) : null;
            if ($minutes !== null) {
                $waits[] = $minutes;
            }

            return [
                $r->created_at->format('d/m/Y H:i'), $r->conversation?->customer?->code, $r->conversation?->customer?->full_name ?? $r->conversation?->contact_name,
                '+'.$r->conversation?->phone, $taken?->user?->name ?? 'Sin atender', $taken?->created_at->format('d/m/Y H:i'), $minutes, $r->conversation?->status->label(),
            ];
        });

        return [
            'headings' => ['Fecha de solicitud', 'Código', 'Cliente', 'Teléfono', 'Atendida por', 'Fecha de atención', 'Espera (min)', 'Estado actual'],
            'rows' => $rows->all(),
            'total' => $total,
            'summary' => ['Solicitudes' => number_format($total), 'Espera promedio' => $waits ? round(array_sum($waits) / count($waits)).' min' : '—'],
        ];
    }
}
