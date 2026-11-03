<?php

namespace App\Livewire\Customers;

use App\Enums\ArticleStatus;
use App\Enums\CustomerStatus;
use App\Enums\MessageStatus;
use App\Enums\PaymentMethod;
use App\Enums\StatusChangeSource;
use App\Models\Customer;
use App\Models\CustomerArticle;
use App\Models\DelinquencyStatus;
use App\Models\MessageTemplate;
use App\Services\ActivityLogger;
use App\Services\Conversations\ConversationService;
use App\Services\DelinquencyService;
use App\Services\Messaging\TemplateRenderer;
use App\Services\PaymentService;
use App\Services\WhatsApp\WhatsAppManager;
use Illuminate\Validation\Rule;
use InvalidArgumentException;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;
use RuntimeException;

/**
 * Ficha del cliente: resumen financiero, artículos, pagos e historiales (mora, mensajes, conversaciones).
 */
class Show extends Component
{
    use WithPagination;

    #[Locked]
    public int $customerId;

    #[Url]
    public string $tab = 'articles';

    // Modal de artículo
    public bool $showArticleModal = false;

    #[Locked]
    public ?int $editingArticleId = null;

    public array $article = [];

    // Modal de pago
    public bool $showPaymentModal = false;

    #[Locked]
    public ?int $paymentArticleId = null;

    public array $payment = [];

    // Modal de cambio de estado
    public bool $showStatusModal = false;

    public ?string $newStatusId = '';

    public int $newDays = 0;

    public string $statusReason = '';

    // Modal de mensaje individual
    public bool $showMessageModal = false;

    public string $messageTemplateId = '';

    public string $messageBody = '';

    public bool $assignToMe = true;

    public function mount(Customer $customer): void
    {
        $this->authorize('customers.view');
        $this->customerId = $customer->id;
    }

    public function setTab(string $tab): void
    {
        $this->tab = $tab;
        $this->resetPage();
    }

    protected function customer(): Customer
    {
        return Customer::findOrFail($this->customerId);
    }

    /* ------------------------- Mensaje individual ------------------------- */

    public function openMessage(): void
    {
        $this->authorize('messages.send');
        $this->resetValidation();
        $this->reset('messageTemplateId', 'messageBody');
        $this->assignToMe = true;
        $this->showMessageModal = true;
    }

    /** Al elegir una plantilla, su contenido se rellena con los datos reales del cliente (editable antes de enviar). */
    public function updatedMessageTemplateId(TemplateRenderer $renderer): void
    {
        $template = $this->messageTemplateId !== '' ? MessageTemplate::active()->find($this->messageTemplateId) : null;
        if ($template) {
            $customer = Customer::with(['articles', 'delinquencyStatus'])->findOrFail($this->customerId);
            $this->messageBody = $renderer->render($template->body, $customer);
        }
    }

    public function sendMessage(ConversationService $conversations): void
    {
        $this->authorize('messages.send');
        $this->validate([
            'messageBody' => ['required', 'string', 'min:2', 'max:4096'],
            'messageTemplateId' => ['nullable', Rule::exists('message_templates', 'id')->where('is_active', true)],
        ], attributes: ['messageBody' => 'mensaje', 'messageTemplateId' => 'plantilla']);

        try {
            $message = $conversations->sendDirectMessage(
                $this->customer(),
                auth()->user(),
                trim($this->messageBody),
                $this->messageTemplateId !== '' ? MessageTemplate::find($this->messageTemplateId) : null,
                $this->assignToMe,
            );
        } catch (RuntimeException $e) {
            $this->addError('messageBody', $e->getMessage());

            return;
        }

        $this->showMessageModal = false;
        $this->reset('messageTemplateId', 'messageBody');

        $message->status === MessageStatus::Failed
            ? $this->dispatch('alert', type: 'error', title: 'No se pudo entregar', message: $message->error_message ?: 'El proveedor rechazó el mensaje.')
            : $this->dispatch('toast', type: 'success', message: 'Mensaje enviado a '.$this->customer()->full_name);
    }

    /* ------------------------------ Artículos ------------------------------ */

    public function createArticle(): void
    {
        $this->authorize('customers.manage');
        $this->resetValidation();
        $this->editingArticleId = null;
        $this->article = [
            'article_code' => '', 'description' => '', 'contract_number' => '', 'acquired_at' => now()->toDateString(),
            'original_price' => '', 'down_payment' => '0', 'installments_count' => '12', 'installment_amount' => '',
            'installments_paid' => '0', 'balance' => '', 'next_payment_date' => now()->addMonth()->toDateString(),
            'status' => ArticleStatus::Active->value,
        ];
        $this->showArticleModal = true;
    }

    public function editArticle(int $id): void
    {
        $this->authorize('customers.manage');
        $this->resetValidation();
        $a = CustomerArticle::where('customer_id', $this->customerId)->findOrFail($id);
        $this->editingArticleId = $a->id;
        $this->article = [
            'article_code' => $a->article_code, 'description' => $a->description, 'contract_number' => $a->contract_number,
            'acquired_at' => $a->acquired_at?->toDateString(), 'original_price' => (string) $a->original_price,
            'down_payment' => (string) $a->down_payment, 'installments_count' => (string) $a->installments_count,
            'installment_amount' => (string) $a->installment_amount, 'installments_paid' => (string) $a->installments_paid,
            'balance' => (string) $a->balance, 'next_payment_date' => $a->next_payment_date?->toDateString(),
            'status' => $a->status->value,
        ];
        $this->showArticleModal = true;
    }

    /** Sugiere el saldo pendiente = cuotas pendientes × valor de cuota. */
    public function suggestBalance(): void
    {
        $pending = max(0, (int) ($this->article['installments_count'] ?? 0) - (int) ($this->article['installments_paid'] ?? 0));
        $this->article['balance'] = number_format($pending * (float) ($this->article['installment_amount'] ?? 0), 2, '.', '');
    }

    public function saveArticle(): void
    {
        $this->authorize('customers.manage');
        $data = $this->validate([
            'article.article_code' => ['required', 'string', 'max:50'],
            'article.description' => ['required', 'string', 'max:255'],
            'article.contract_number' => ['required', 'string', 'max:50',
                Rule::unique('customer_articles', 'contract_number')
                    ->where('customer_id', $this->customerId)
                    ->where('article_code', $this->article['article_code'] ?? '')
                    ->ignore($this->editingArticleId)],
            'article.acquired_at' => ['nullable', 'date', 'before_or_equal:today'],
            'article.original_price' => ['required', 'numeric', 'min:0', 'max:9999999'],
            'article.down_payment' => ['required', 'numeric', 'min:0', 'lte:article.original_price'],
            'article.installments_count' => ['required', 'integer', 'min:0', 'max:600'],
            'article.installment_amount' => ['required', 'numeric', 'min:0'],
            'article.installments_paid' => ['required', 'integer', 'min:0', 'lte:article.installments_count'],
            'article.balance' => ['required', 'numeric', 'min:0'],
            'article.next_payment_date' => ['nullable', 'date'],
            'article.status' => ['required', Rule::enum(ArticleStatus::class)],
        ], attributes: [
            'article.article_code' => 'código de artículo', 'article.description' => 'descripción', 'article.contract_number' => 'número de contrato',
            'article.acquired_at' => 'fecha de adquisición', 'article.original_price' => 'precio original', 'article.down_payment' => 'prima',
            'article.installments_count' => 'cantidad de cuotas', 'article.installment_amount' => 'valor de cuota',
            'article.installments_paid' => 'cuotas pagadas', 'article.balance' => 'saldo pendiente', 'article.next_payment_date' => 'próxima fecha de pago',
        ])['article'];

        $article = $this->editingArticleId
            ? CustomerArticle::where('customer_id', $this->customerId)->findOrFail($this->editingArticleId)
            : new CustomerArticle(['customer_id' => $this->customerId]);

        $article->fill($data + ['acquired_at' => null, 'next_payment_date' => null]);
        $article->acquired_at = $data['acquired_at'] ?: null;
        $article->next_payment_date = $data['next_payment_date'] ?: null;
        $article->save();

        app(DelinquencyService::class)->updateArticleDays($this->customer());

        ActivityLogger::log(
            $this->editingArticleId ? 'customer.article_updated' : 'customer.article_created',
            ($this->editingArticleId ? 'Actualizó' : 'Agregó')." el artículo {$article->description} (contrato {$article->contract_number})",
            $this->customer(),
        );

        $this->showArticleModal = false;
        $this->dispatch('toast', type: 'success', message: $this->editingArticleId ? 'Artículo actualizado' : 'Artículo agregado');
    }

    public function deleteArticle(int $id): void
    {
        $this->authorize('customers.manage');
        $article = CustomerArticle::where('customer_id', $this->customerId)->withCount('payments')->findOrFail($id);

        if ($article->payments_count > 0) {
            $this->dispatch('alert', type: 'error', title: 'No se puede eliminar', message: 'El artículo tiene pagos registrados. Cámbialo a estado "Anulado" en su lugar.');

            return;
        }

        $article->delete();
        ActivityLogger::log('customer.article_deleted', "Eliminó el artículo {$article->description} (contrato {$article->contract_number})", $this->customer());
        $this->dispatch('toast', type: 'success', message: 'Artículo eliminado');
    }

    /* ------------------------------- Pagos -------------------------------- */

    public function openPayment(int $articleId): void
    {
        $this->authorize('customers.manage');
        $this->resetValidation();
        $a = CustomerArticle::where('customer_id', $this->customerId)->findOrFail($articleId);
        $this->paymentArticleId = $a->id;
        $this->payment = [
            'amount' => number_format(min((float) $a->installment_amount, (float) $a->balance), 2, '.', ''),
            'paid_at' => now()->toDateString(),
            'method' => PaymentMethod::Cash->value,
            'reference' => '',
            'notes' => '',
        ];
        $this->showPaymentModal = true;
    }

    public function savePayment(PaymentService $payments): void
    {
        $this->authorize('customers.manage');
        $data = $this->validate([
            'payment.amount' => ['required', 'numeric', 'gt:0'],
            'payment.paid_at' => ['required', 'date', 'before_or_equal:today'],
            'payment.method' => ['required', Rule::enum(PaymentMethod::class)],
            'payment.reference' => ['nullable', 'string', 'max:100'],
            'payment.notes' => ['nullable', 'string', 'max:500'],
        ], attributes: ['payment.amount' => 'monto', 'payment.paid_at' => 'fecha de pago', 'payment.method' => 'forma de pago'])['payment'];

        $article = CustomerArticle::where('customer_id', $this->customerId)->findOrFail($this->paymentArticleId);

        try {
            $payments->register($article, (float) $data['amount'], $data['paid_at'], PaymentMethod::from($data['method']), $data['reference'] ?: null, $data['notes'] ?: null, auth()->user());
        } catch (InvalidArgumentException $e) {
            $this->addError('payment.amount', $e->getMessage());

            return;
        }

        $this->showPaymentModal = false;
        $this->dispatch('toast', type: 'success', message: 'Pago registrado: '.money($data['amount']));
    }

    /* --------------------------- Estado de mora --------------------------- */

    public function openStatusModal(): void
    {
        $this->authorize('customers.manage');
        $customer = $this->customer();
        $this->resetValidation();
        $this->newStatusId = (string) $customer->delinquency_status_id;
        $this->newDays = $customer->days_overdue;
        $this->statusReason = '';
        $this->showStatusModal = true;
    }

    public function saveStatus(DelinquencyService $service): void
    {
        $this->authorize('customers.manage');
        $this->validate([
            'newStatusId' => ['required', 'exists:delinquency_statuses,id'],
            'newDays' => ['required', 'integer', 'min:0', 'max:5000'],
            'statusReason' => ['required', 'string', 'min:5', 'max:500'],
        ], attributes: ['newStatusId' => 'estado', 'newDays' => 'días de mora', 'statusReason' => 'motivo']);

        $changed = $service->changeStatus($this->customer(), DelinquencyStatus::find($this->newStatusId), $this->newDays, StatusChangeSource::Manual, $this->statusReason, auth()->user());

        $this->showStatusModal = false;
        $this->dispatch('toast', type: $changed ? 'success' : 'info', message: $changed ? 'Estado de mora actualizado' : 'Sin cambios en el estado (días actualizados)');
    }

    public function recalculateStatus(DelinquencyService $service): void
    {
        $this->authorize('customers.manage');
        $customer = $this->customer();
        if ($customer->delinquencyStatus && ! $customer->delinquencyStatus->is_auto_assignable) {
            $this->dispatch('alert', type: 'info', title: 'Estado manual', message: "El estado “{$customer->delinquencyStatus->name}” es de asignación manual y no se recalcula automáticamente.");

            return;
        }
        $changed = $service->recalculate($customer, auth()->user());
        $this->dispatch('toast', type: 'success', message: $changed ? 'Estado recalculado según fechas de pago' : 'El estado ya corresponde a las fechas de pago');
    }

    public function toggleActive(): void
    {
        $this->authorize('customers.manage');
        $customer = $this->customer();
        $newStatus = $customer->isActive() ? CustomerStatus::Inactive : CustomerStatus::Active;
        $customer->update(['status' => $newStatus, 'updated_by' => auth()->id()]);
        ActivityLogger::log($newStatus === CustomerStatus::Active ? 'customer.activated' : 'customer.deactivated',
            ($newStatus === CustomerStatus::Active ? 'Reactivó' : 'Desactivó')." al cliente {$customer->full_name}", $customer);
        $this->dispatch('toast', type: 'success', message: $newStatus === CustomerStatus::Active ? 'Cliente reactivado' : 'Cliente desactivado');
    }

    public function render()
    {
        $customer = Customer::query()
            ->with(['delinquencyStatus', 'groups:id,name,color', 'creator:id,name'])
            ->findOrFail($this->customerId);

        $articles = $customer->articles()->orderByRaw('balance = 0')->orderBy('next_payment_date')->get();

        $tabData = match ($this->tab) {
            'payments' => $customer->payments()->with(['article:id,description,contract_number', 'registeredBy:id,name'])->latest('paid_at')->latest('id')->paginate(10),
            'history' => $customer->statusHistories()->with(['fromStatus:id,name,color', 'toStatus:id,name,color', 'user:id,name'])->latest('id')->paginate(10),
            'messages' => $customer->messages()->with(['campaign:id,name', 'user:id,name'])->latest('id')->paginate(10),
            'conversations' => $customer->conversations()->with('assignee:id,name')->withCount('entries')->latest('last_message_at')->paginate(10),
            default => null,
        };

        return view('livewire.customers.show', [
            'customer' => $customer,
            'articles' => $articles,
            'tabData' => $tabData,
            'summary' => [
                'original' => $articles->sum(fn ($a) => (float) $a->original_price),
                'paid' => (float) $customer->payments()->sum('amount'),
                'active_articles' => $articles->whereIn('status', [ArticleStatus::Active, ArticleStatus::InCollection])->count(),
                'monthly' => $articles->where('balance', '>', 0)->sum(fn ($a) => (float) $a->installment_amount),
            ],
            'statuses' => DelinquencyStatus::query()->active()->ordered()->get(['id', 'name', 'min_days_overdue', 'is_auto_assignable']),
            'messageTemplates' => $this->showMessageModal
                ? MessageTemplate::query()->active()->orderBy('category')->orderBy('name')->get(['id', 'name', 'category', 'provider_template_name', 'twilio_content_sid'])
                : collect(),
            'messageContext' => $this->showMessageModal ? [
                'simulation' => app(WhatsAppManager::class)->isSimulation(),
                'provider' => app(WhatsAppManager::class)->activeName(),
                'conversation' => $customer->conversations()->latest('last_message_at')->first(['id', 'status', 'last_inbound_at', 'assigned_to', 'is_simulated']),
            ] : null,
            'counts' => [
                'payments' => $customer->payments()->count(),
                'history' => $customer->statusHistories()->count(),
                'messages' => $customer->messages()->count(),
                'conversations' => $customer->conversations()->count(),
            ],
        ])->title($customer->full_name);
    }
}
