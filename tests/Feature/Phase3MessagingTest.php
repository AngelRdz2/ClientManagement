<?php

namespace Tests\Feature;

use App\Enums\CampaignStatus;
use App\Enums\CampaignType;
use App\Enums\MessageStatus;
use App\Enums\RecipientStatus;
use App\Enums\SenderType;
use App\Livewire\Campaigns\Builder;
use App\Livewire\Promotions\Builder as PromotionBuilder;
use App\Livewire\Templates\Index as Templates;
use App\Models\Campaign;
use App\Models\Customer;
use App\Models\DelinquencyStatus;
use App\Models\Message;
use App\Models\MessageTemplate;
use App\Services\Messaging\CampaignAudience;
use App\Services\Messaging\CampaignService;
use App\Services\Messaging\MessageSender;
use App\Services\Messaging\TemplateRenderer;
use App\Services\Settings;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class Phase3MessagingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedBase(demoCustomers: 40);
        app(Settings::class)->set('whatsapp.simulator_failure_rate', 0);
        // Dentro de la ventana de envío de cobranza (08:00–19:00)
        $this->travelTo(CarbonImmutable::parse('2026-11-03 10:00:00', 'America/El_Salvador'));
    }

    public function test_pages_render(): void
    {
        $admin = $this->admin();
        foreach (['templates.index', 'campaigns.index', 'campaigns.create', 'promotions.index', 'promotions.create', 'messages.index'] as $route) {
            $this->actingAs($admin)->get(route($route))->assertOk();
        }
    }

    public function test_template_renderer_uses_real_customer_data(): void
    {
        $customer = Customer::where('dui', '01234567-8')->firstOrFail();
        $renderer = app(TemplateRenderer::class);

        $text = $renderer->render('Hola {{nombre}}, saldo ${{saldo_pendiente}} ({{cantidad_articulos}} artículos). {{desconocida}}', $customer);
        $this->assertSame('Hola María Fernanda López Hernández, saldo $430.00 (2 artículos). {{desconocida}}', $text);

        $list = $renderer->render('{{lista_articulos}}', $customer);
        $this->assertStringContainsString('Televisor Samsung 55" 4K', $list);
        $this->assertStringContainsString('Saldo: $250.00 · Cuotas pendientes: 5', $list);
        $this->assertSame(['desconocida'], $renderer->unknownVariables('{{nombre}} {{desconocida}}'));
        $this->assertSame(['María Fernanda López Hernández', '430.00'], $renderer->orderedValues('{{nombre}} {{saldo_pendiente}}', $customer));
    }

    public function test_template_crud_rejects_unknown_variables(): void
    {
        Livewire::actingAs($this->admin())->test(Templates::class)
            ->call('create', 'collection')
            ->set('form.name', 'Prueba')
            ->set('form.body', 'Hola {{nombre}} {{variable_rara}}')
            ->call('save')
            ->assertHasErrors('form.body')
            ->set('form.body', 'Hola {{nombre}}, su saldo es ${{saldo_pendiente}}')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('message_templates', ['name' => 'Prueba', 'category' => 'collection']);
    }

    public function test_collection_campaign_with_status_templates_excludes_solvent_customers(): void
    {
        $component = Livewire::actingAs($this->admin())->test(Builder::class)
            ->set('name', 'Cobranza noviembre')
            ->assertSet('use_status_templates', true)
            ->call('save', true)
            ->assertHasNoErrors();

        $campaign = Campaign::latest('id')->firstOrFail();
        $this->assertSame(CampaignStatus::Completed, $campaign->status);

        // Ningún cliente solvente recibió mensaje
        $solventIds = Customer::whereHas('delinquencyStatus', fn ($q) => $q->where('is_solvent', true))->pluck('id');
        $this->assertSame(0, $campaign->recipients()->whereIn('customer_id', $solventIds)->count());

        $expected = app(CampaignAudience::class)->eligible(CampaignType::Collection, [])->count();
        $this->assertSame($expected, $campaign->total_recipients);
        $this->assertSame($campaign->total_recipients, $campaign->sent_count + $campaign->failed_count + $campaign->skipped_count);

        // Cada cliente recibió la plantilla de su estado
        $maria = Customer::where('dui', '01234567-8')->first();
        $recipient = $campaign->recipients()->where('customer_id', $maria->id)->first();
        $this->assertStringContainsString('Le recordamos amablemente', $recipient->rendered_body); // plantilla de Mora 10
        $this->assertStringContainsString('$430.00', $recipient->rendered_body);
        $this->assertSame(RecipientStatus::Sent, $recipient->status);
        $this->assertSame(MessageStatus::Delivered, $recipient->message->status);
        $this->assertSame('simulator', $recipient->message->provider);
        $this->assertStringStartsWith('sim_', $recipient->message->provider_message_id);

        // El número terminado en 0000 siempre falla en el simulador
        $failing = Customer::where('phone_primary', '7777-0000')->first();
        if ($failing && $failing->canReceiveCollectionMessages()) {
            $this->assertSame(RecipientStatus::Failed, $campaign->recipients()->where('customer_id', $failing->id)->first()->status);
        }

        // Notificación de campaña finalizada al creador
        $this->assertTrue($this->admin()->notifications()->where('type', 'campaign_finished')->exists());
    }

    public function test_duplicate_collection_messages_are_prevented_within_window(): void
    {
        $service = app(CampaignService::class);
        $make = fn (string $name) => Campaign::create([
            'name' => $name, 'type' => CampaignType::Collection, 'use_status_templates' => true, 'filters' => [],
            'status' => CampaignStatus::Draft, 'created_by' => $this->admin()->id,
        ]);

        $first = $make('Primera');
        $service->launch($first);
        $second = $make('Segunda');
        $service->launch($second);

        $second->refresh();
        $first->refresh();
        // Solo quienes recibieron con éxito el primer mensaje se omiten; un envío fallido no cuenta como contacto.
        $this->assertSame(0, $second->sent_count);
        $this->assertSame($first->sent_count, $second->skipped_count);
        $this->assertSame($first->failed_count, $second->failed_count);
        $this->assertStringContainsString('últimas 24 horas', $second->recipients()->where('status', RecipientStatus::Skipped)->first()->skip_reason);
    }

    public function test_idempotency_key_prevents_duplicate_messages(): void
    {
        $sender = app(MessageSender::class);
        $customer = Customer::first();
        $a = $sender->create($customer->whatsapp_number, 'Hola', SenderType::Campaign, ['customer_id' => $customer->id], 'test:key:1');
        $b = $sender->create($customer->whatsapp_number, 'Hola', SenderType::Campaign, ['customer_id' => $customer->id], 'test:key:1');
        $this->assertSame($a->id, $b->id);
        $this->assertSame(1, Message::where('idempotency_key', 'test:key:1')->count());

        $sender->deliver($a);
        $sender->deliver($a->fresh()); // segundo intento no reenvía
        $this->assertSame(1, $a->fresh()->attempts);
    }

    public function test_promotion_campaign_only_reaches_customers_with_consent(): void
    {
        $template = MessageTemplate::where('category', 'promotion')->first();
        $optedOut = Customer::where('accepts_promotions', true)->whereNull('whatsapp_opted_out_at')->first();
        $optedOut->update(['whatsapp_opted_out_at' => now()]);

        Livewire::actingAs($this->admin())->test(PromotionBuilder::class)
            ->assertSet('type', 'promotion')
            ->set('name', 'Promo $0 prima')
            ->set('message_template_id', (string) $template->id)
            ->call('save', true)
            ->assertHasNoErrors();

        $campaign = Campaign::where('name', 'Promo $0 prima')->firstOrFail();
        $this->assertSame(CampaignType::Promotion, $campaign->type);
        $ids = $campaign->recipients()->pluck('customer_id');
        $this->assertGreaterThan(0, $ids->count());
        $this->assertSame(0, Customer::whereIn('id', $ids)->where('accepts_promotions', false)->count());
        $this->assertFalse($ids->contains($optedOut->id));
    }

    public function test_promotion_builder_rejects_collection_templates(): void
    {
        $collectionTemplate = MessageTemplate::where('category', 'collection')->first();
        Livewire::actingAs($this->admin())->test(PromotionBuilder::class)
            ->set('name', 'Intento')
            ->set('message_template_id', (string) $collectionTemplate->id)
            ->call('save', false)
            ->assertHasErrors('message_template_id');
    }

    public function test_scheduled_campaign_is_started_by_scheduler_command(): void
    {
        $template = MessageTemplate::where('category', 'reminder')->first();
        Livewire::actingAs($this->admin())->test(Builder::class)
            ->set('name', 'Recordatorio programado')
            ->set('type', 'reminder')
            ->set('message_template_id', (string) $template->id)
            ->set('filters.delinquency_status_ids', [(string) DelinquencyStatus::where('code', 'MORA_10')->value('id')])
            ->set('when', 'later')
            ->set('scheduled_at', now()->addHour()->format('Y-m-d\TH:i'))
            ->call('save', true)
            ->assertHasNoErrors();

        $campaign = Campaign::where('name', 'Recordatorio programado')->firstOrFail();
        $this->assertSame(CampaignStatus::Scheduled, $campaign->status);

        $this->artisan('campaigns:dispatch-scheduled')->assertSuccessful();
        $this->assertSame(CampaignStatus::Scheduled, $campaign->fresh()->status);

        $this->travel(2)->hours();
        $this->artisan('campaigns:dispatch-scheduled')->assertSuccessful();
        $this->assertSame(CampaignStatus::Completed, $campaign->fresh()->status);
    }

    public function test_cancel_draft_campaign(): void
    {
        $campaign = Campaign::create(['name' => 'X', 'type' => CampaignType::Collection, 'use_status_templates' => true, 'filters' => [], 'status' => CampaignStatus::Draft, 'created_by' => $this->admin()->id]);
        app(CampaignService::class)->cancel($campaign);
        $this->assertSame(CampaignStatus::Cancelled, $campaign->fresh()->status);
        $this->actingAs($this->admin())->get(route('campaigns.show', $campaign))->assertOk()->assertSee('Cancelada');
    }
}
