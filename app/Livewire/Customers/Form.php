<?php

namespace App\Livewire\Customers;

use App\Enums\CustomerStatus;
use App\Enums\StatusChangeSource;
use App\Models\Customer;
use App\Models\CustomerGroup;
use App\Models\DelinquencyStatus;
use App\Rules\ValidDui;
use App\Rules\ValidPhone;
use App\Services\ActivityLogger;
use App\Services\DelinquencyService;
use App\Support\Dui;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * Registro y edición de clientes. El estado de mora inicial se asigna al crear; los cambios posteriores
 * se realizan con "Cambiar estado" (para registrar motivo en el historial).
 */
class Form extends Component
{
    #[Locked]
    public ?int $customerId = null;

    public string $code = '';

    public string $full_name = '';

    public string $dui = '';

    public string $phone_primary = '';

    public ?string $phone_secondary = '';

    public ?string $email = '';

    public ?string $address = '';

    public string $registered_at = '';

    public string $status = 'active';

    public ?string $delinquency_status_id = '';

    public int $days_overdue = 0;

    public bool $accepts_collection_messages = true;

    public bool $accepts_promotions = false;

    public ?string $notes = '';

    public array $group_ids = [];

    public function mount(?Customer $customer = null): void
    {
        $this->authorize('customers.manage');

        if ($customer?->exists) {
            $this->customerId = $customer->id;
            $this->fill($customer->only([
                'code', 'full_name', 'dui', 'phone_primary', 'phone_secondary', 'email', 'address', 'notes',
                'accepts_collection_messages', 'accepts_promotions', 'days_overdue',
            ]));
            $this->registered_at = $customer->registered_at->toDateString();
            $this->status = $customer->status->value;
            $this->delinquency_status_id = (string) $customer->delinquency_status_id;
            $this->group_ids = $customer->groups()->pluck('customer_groups.id')->map(fn ($id) => (string) $id)->all();
        } else {
            $this->code = $this->nextCode();
            $this->registered_at = now()->toDateString();
            $this->delinquency_status_id = (string) DelinquencyStatus::where('is_solvent', true)->active()->value('id');
        }
    }

    protected function rules(): array
    {
        return [
            'code' => ['required', 'string', 'max:30', Rule::unique('customers', 'code')->ignore($this->customerId)],
            'full_name' => ['required', 'string', 'min:5', 'max:200'],
            'dui' => ['required', new ValidDui, Rule::unique('customers', 'dui')->ignore($this->customerId)],
            'phone_primary' => ['required', new ValidPhone],
            'phone_secondary' => ['nullable', new ValidPhone],
            'email' => ['nullable', 'email', 'max:150'],
            'address' => ['nullable', 'string', 'max:500'],
            'registered_at' => ['required', 'date', 'before_or_equal:today'],
            'status' => ['required', Rule::enum(CustomerStatus::class)],
            'delinquency_status_id' => [$this->customerId ? 'nullable' : 'required', 'exists:delinquency_statuses,id'],
            'days_overdue' => ['integer', 'min:0', 'max:5000'],
            'accepts_collection_messages' => ['boolean'],
            'accepts_promotions' => ['boolean'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'group_ids' => ['array'],
            'group_ids.*' => ['exists:customer_groups,id'],
        ];
    }

    protected function validationAttributes(): array
    {
        return [
            'code' => 'código de cliente', 'full_name' => 'nombre completo', 'dui' => 'DUI', 'phone_primary' => 'teléfono principal',
            'phone_secondary' => 'teléfono secundario', 'email' => 'correo electrónico', 'address' => 'dirección',
            'registered_at' => 'fecha de registro', 'delinquency_status_id' => 'estado de mora', 'days_overdue' => 'días de mora',
        ];
    }

    public function updatedDui(): void
    {
        if ($normalized = Dui::normalize($this->dui)) {
            $this->dui = $normalized;
        }
    }

    public function save(DelinquencyService $delinquency)
    {
        $this->dui = Dui::normalize($this->dui) ?? $this->dui;
        $data = $this->validate();
        $isNew = $this->customerId === null;

        $customer = DB::transaction(function () use ($data, $isNew, $delinquency) {
            $customer = $isNew ? new Customer : Customer::findOrFail($this->customerId);
            $wasAcceptingPromotions = (bool) $customer->accepts_promotions;

            $customer->fill([
                'code' => trim($data['code']),
                'full_name' => trim($data['full_name']),
                'dui' => $data['dui'],
                'phone_primary' => trim($data['phone_primary']),
                'phone_secondary' => $data['phone_secondary'] ?: null,
                'email' => $data['email'] ?: null,
                'address' => $data['address'] ?: null,
                'registered_at' => $data['registered_at'],
                'status' => $data['status'],
                'accepts_collection_messages' => $data['accepts_collection_messages'],
                'accepts_promotions' => $data['accepts_promotions'],
                'notes' => $data['notes'] ?: null,
                $isNew ? 'created_by' : 'updated_by' => auth()->id(),
            ]);

            // Consentimiento de promociones: se registra cuándo y por qué medio se otorgó.
            if ($data['accepts_promotions'] && ! $wasAcceptingPromotions) {
                $customer->promotions_consent_at = now();
                $customer->promotions_consent_source = 'registro manual';
            } elseif (! $data['accepts_promotions']) {
                $customer->promotions_consent_at = null;
                $customer->promotions_consent_source = null;
            }

            $customer->save();
            $customer->groups()->sync($data['group_ids']);

            if ($isNew) {
                $delinquency->changeStatus(
                    $customer,
                    DelinquencyStatus::find($data['delinquency_status_id']),
                    (int) $data['days_overdue'],
                    StatusChangeSource::Manual,
                    'Estado inicial al registrar el cliente',
                    auth()->user(),
                );
            }

            return $customer;
        });

        ActivityLogger::log(
            $isNew ? 'customer.created' : 'customer.updated',
            ($isNew ? 'Registró' : 'Actualizó')." al cliente {$customer->full_name} ({$customer->code})",
            $customer,
        );

        session()->flash('toast', $isNew ? 'Cliente registrado correctamente' : 'Cliente actualizado correctamente');

        return $this->redirectRoute('customers.show', $customer, navigate: true);
    }

    private function nextCode(): string
    {
        $last = Customer::where('code', 'like', 'CLI-%')->orderByDesc('code')->value('code');
        $number = $last ? ((int) substr($last, 4)) + 1 : 1;

        return 'CLI-'.str_pad((string) $number, 6, '0', STR_PAD_LEFT);
    }

    public function render()
    {
        return view('livewire.customers.form', [
            'statuses' => DelinquencyStatus::query()->active()->ordered()->get(['id', 'name', 'min_days_overdue']),
            'groups' => CustomerGroup::orderBy('name')->get(['id', 'name', 'color']),
            'isNew' => $this->customerId === null,
        ])->title($this->customerId ? 'Editar cliente' : 'Nuevo cliente');
    }
}
