<?php

namespace App\Livewire\Campaigns;

use App\Enums\CampaignStatus;
use App\Enums\CampaignType;
use App\Enums\TemplateCategory;
use App\Models\Campaign;
use App\Models\Customer;
use App\Models\CustomerGroup;
use App\Models\DelinquencyStatus;
use App\Models\MessageTemplate;
use App\Services\ActivityLogger;
use App\Services\Messaging\CampaignAudience;
use App\Services\Messaging\CampaignService;
use App\Services\Messaging\TemplateRenderer;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * Constructor de campañas: datos generales, segmentación, plantilla y previsualización de destinatarios y mensajes.
 */
class Builder extends Component
{
    #[Locked]
    public string $mode = 'collection';

    #[Locked]
    public ?int $campaignId = null;

    public string $name = '';

    public string $type = 'collection';

    public ?string $message_template_id = '';

    public bool $use_status_templates = true;

    public string $when = 'now';

    public ?string $scheduled_at = '';

    public array $filters = CampaignAudience::FILTER_DEFAULTS;

    public string $customerSearch = '';

    public function mount(?Campaign $campaign = null): void
    {
        $this->authorize('campaigns.manage');

        if ($campaign?->exists) {
            abort_unless($campaign->isEditable(), 403, 'La campaña ya no se puede editar.');
            $this->campaignId = $campaign->id;
            $this->name = $campaign->name;
            $this->type = $campaign->type->value;
            $this->message_template_id = (string) $campaign->message_template_id;
            $this->use_status_templates = $campaign->use_status_templates;
            $this->filters = array_merge(CampaignAudience::FILTER_DEFAULTS, $campaign->filters ?? []);
            $this->when = $campaign->scheduled_at ? 'later' : 'now';
            $this->scheduled_at = $campaign->scheduled_at?->format('Y-m-d\TH:i');
        } else {
            $this->type = $this->mode === 'promotion' ? CampaignType::Promotion->value : CampaignType::Collection->value;
            $this->use_status_templates = $this->mode !== 'promotion';
            if ($this->mode === 'promotion') {
                $this->filters['solvent_only'] = false;
            }
        }
    }

    public function updatedType(): void
    {
        if ($this->type !== CampaignType::Collection->value) {
            $this->use_status_templates = false;
        }
        $this->message_template_id = '';
    }

    public function addCustomer(int $id): void
    {
        $this->filters['customer_ids'] = array_values(array_unique([...$this->filters['customer_ids'], (string) $id]));
        $this->customerSearch = '';
    }

    public function removeCustomer(string $id): void
    {
        $this->filters['customer_ids'] = array_values(array_diff($this->filters['customer_ids'], [$id]));
    }

    public function resetFilters(): void
    {
        $this->filters = CampaignAudience::FILTER_DEFAULTS;
    }

    protected function campaignType(): CampaignType
    {
        return CampaignType::tryFrom($this->type) ?? CampaignType::Collection;
    }

    protected function allowedTypes(): array
    {
        return $this->mode === 'promotion'
            ? [CampaignType::Promotion]
            : [CampaignType::Collection, CampaignType::Reminder, CampaignType::Custom];
    }

    protected function templateCategories(): array
    {
        return match ($this->campaignType()) {
            CampaignType::Promotion => [TemplateCategory::Promotion->value],
            CampaignType::Collection => [TemplateCategory::Collection->value, TemplateCategory::Reminder->value],
            CampaignType::Reminder => [TemplateCategory::Reminder->value, TemplateCategory::Collection->value],
            default => array_map(fn ($c) => $c->value, TemplateCategory::cases()),
        };
    }

    public function save(bool $launch = false)
    {
        $usesStatusTemplates = $this->use_status_templates && $this->campaignType() === CampaignType::Collection;

        $this->validate([
            'name' => ['required', 'string', 'min:3', 'max:150'],
            'type' => ['required', Rule::in(array_map(fn ($t) => $t->value, $this->allowedTypes()))],
            'message_template_id' => [$usesStatusTemplates ? 'nullable' : 'required', Rule::exists('message_templates', 'id')->whereIn('category', $this->templateCategories())->where('is_active', true)],
            'when' => ['required', 'in:now,later'],
            'scheduled_at' => [$this->when === 'later' ? 'required' : 'nullable', 'nullable', 'date', 'after:now'],
            'filters.balance_min' => ['nullable', 'numeric', 'min:0'],
            'filters.balance_max' => ['nullable', 'numeric', 'min:0'],
            'filters.days_min' => ['nullable', 'integer', 'min:0'],
            'filters.days_max' => ['nullable', 'integer', 'min:0'],
            'filters.registered_from' => ['nullable', 'date'],
            'filters.registered_to' => ['nullable', 'date'],
        ], attributes: [
            'name' => 'nombre', 'message_template_id' => 'plantilla', 'scheduled_at' => 'fecha de envío',
            'filters.balance_min' => 'saldo mínimo', 'filters.balance_max' => 'saldo máximo',
        ]);

        $eligible = app(CampaignAudience::class)->eligible($this->campaignType(), $this->cleanFilters())->count();
        if ($launch && $eligible === 0) {
            $this->dispatch('alert', type: 'warning', title: 'Sin destinatarios', message: 'Ningún cliente cumple los filtros y las reglas de envío.');

            return null;
        }

        $campaign = $this->campaignId ? Campaign::findOrFail($this->campaignId) : new Campaign(['created_by' => auth()->id(), 'status' => CampaignStatus::Draft]);
        $campaign->fill([
            'name' => $this->name,
            'type' => $this->type,
            'message_template_id' => $usesStatusTemplates ? null : $this->message_template_id,
            'use_status_templates' => $usesStatusTemplates,
            'filters' => $this->cleanFilters(),
            'scheduled_at' => $this->when === 'later' ? $this->scheduled_at : null,
            'total_recipients' => $eligible,
        ])->save();

        ActivityLogger::log('campaign.saved', ($this->campaignId ? 'Actualizó' : 'Creó')." la campaña “{$campaign->name}” ({$eligible} destinatarios estimados)", $campaign);

        if ($launch) {
            app(CampaignService::class)->launch($campaign);
            session()->flash('toast', $campaign->fresh()->status === CampaignStatus::Scheduled ? 'Campaña programada' : 'Campaña iniciada: los mensajes se están enviando');
        } else {
            session()->flash('toast', 'Campaña guardada como borrador');
        }

        return $this->redirectRoute(($this->mode === 'promotion' ? 'promotions' : 'campaigns').'.show', $campaign, navigate: true);
    }

    protected function cleanFilters(): array
    {
        $f = array_merge(CampaignAudience::FILTER_DEFAULTS, $this->filters);
        foreach (['customer_ids', 'group_ids', 'delinquency_status_ids'] as $key) {
            $f[$key] = array_values(array_filter((array) $f[$key], fn ($v) => $v !== '' && $v !== null));
        }
        foreach (['balance_min', 'balance_max', 'days_min', 'days_max', 'registered_from', 'registered_to'] as $key) {
            $f[$key] = $f[$key] === '' ? null : $f[$key];
        }
        $f['solvent_only'] = (bool) $f['solvent_only'];

        return $f;
    }

    public function render(CampaignAudience $audience, TemplateRenderer $renderer)
    {
        $type = $this->campaignType();
        $filters = $this->cleanFilters();
        $summary = $audience->summary($type, $filters);

        $template = $this->message_template_id ? MessageTemplate::find($this->message_template_id) : null;
        $statuses = DelinquencyStatus::query()->ordered()->with('messageTemplate:id,name,body')->get();
        $samples = $audience->eligible($type, $filters)->with(['articles', 'delinquencyStatus'])->orderByDesc('days_overdue')->limit(6)->get();

        $previews = $samples->map(function (Customer $c) use ($template, $statuses, $renderer) {
            $tpl = $this->use_status_templates && $this->campaignType() === CampaignType::Collection
                ? $statuses->firstWhere('id', $c->delinquency_status_id)?->messageTemplate
                : $template;

            return ['customer' => $c, 'template' => $tpl, 'body' => $tpl ? $renderer->render($tpl->body, $c) : null];
        });

        return view('livewire.campaigns.builder', [
            'types' => $this->allowedTypes(),
            'templates' => MessageTemplate::query()->active()->whereIn('category', $this->templateCategories())->orderBy('name')->get(['id', 'name', 'category']),
            'statuses' => $statuses,
            'groups' => CustomerGroup::withCount('customers')->orderBy('name')->get(),
            'summary' => $summary,
            'previews' => $previews,
            'selectedCustomers' => $filters['customer_ids'] ? Customer::whereIn('id', $filters['customer_ids'])->get(['id', 'code', 'full_name']) : collect(),
            'customerCandidates' => mb_strlen($this->customerSearch) >= 2 ? Customer::search($this->customerSearch)->limit(6)->get(['id', 'code', 'full_name']) : collect(),
            'prefix' => $this->mode === 'promotion' ? 'promotions' : 'campaigns',
            'isPromotion' => $this->mode === 'promotion',
        ])->title($this->campaignId ? 'Editar campaña' : 'Nueva campaña');
    }
}
