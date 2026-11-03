<?php

namespace Tests\Feature;

use App\Enums\ImportStatus;
use App\Enums\PaymentMethod;
use App\Enums\StatusChangeSource;
use App\Jobs\RecalculateDelinquencyJob;
use App\Livewire\Customers\Form;
use App\Livewire\Customers\Import;
use App\Livewire\Customers\Index;
use App\Livewire\Customers\Show;
use App\Livewire\Delinquency\Statuses;
use App\Livewire\Delinquency\Updates;
use App\Models\Customer;
use App\Models\CustomerArticle;
use App\Models\CustomerStatusHistory;
use App\Models\DelinquencyStatus;
use App\Models\ImportBatch;
use App\Models\User;
use App\Services\DelinquencyService;
use App\Services\Import\CustomerImportService;
use App\Services\PaymentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use LogicException;
use Tests\TestCase;

class Phase2CustomersTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedBase(demoCustomers: 12);
    }

    private function statusByCode(string $code): DelinquencyStatus
    {
        return DelinquencyStatus::where('code', $code)->firstOrFail();
    }

    public function test_customer_pages_render(): void
    {
        $admin = $this->admin();
        $customer = Customer::first();

        $this->actingAs($admin)->get(route('customers.index'))->assertOk()->assertSee($customer->full_name);
        $this->actingAs($admin)->get(route('customers.show', $customer))->assertOk()->assertSee('Saldo pendiente total');
        $this->actingAs($admin)->get(route('customers.create'))->assertOk();
        $this->actingAs($admin)->get(route('customers.edit', $customer))->assertOk();
        $this->actingAs($admin)->get(route('delinquency.index'))->assertOk()->assertSee('Mora 30 días');
        $this->actingAs($admin)->get(route('delinquency.updates'))->assertOk();
        $this->actingAs($admin)->get(route('delinquency.history'))->assertOk();
        $this->actingAs($admin)->get(route('customers.import'))->assertOk();
        $this->actingAs($admin)->get(route('groups.index'))->assertOk();

        foreach (['payments', 'history', 'messages', 'conversations'] as $tab) {
            Livewire::actingAs($admin)->test(Show::class, ['customer' => $customer])->call('setTab', $tab)->assertOk();
        }
    }

    public function test_agent_cannot_manage_customers(): void
    {
        $agent = User::where('email', 'asesor@crm.test')->first();
        $this->actingAs($agent)->get(route('customers.index'))->assertOk();
        $this->actingAs($agent)->get(route('customers.create'))->assertForbidden();
        $this->actingAs($agent)->get(route('customers.import'))->assertForbidden();
        $this->actingAs($agent)->get(route('delinquency.index'))->assertForbidden();
    }

    public function test_search_by_name_dui_phone_and_code(): void
    {
        $c = Customer::first();
        foreach ([$c->dui, $c->code, substr($c->phone_primary, 0, 4), explode(' ', $c->full_name)[0]] as $term) {
            Livewire::actingAs($this->admin())->test(Index::class)->set('search', $term)->assertSee($c->full_name);
        }
    }

    public function test_create_customer_with_validation_and_initial_history(): void
    {
        Livewire::actingAs($this->admin())->test(Form::class)
            ->set('full_name', 'Pedro Pablo Prueba')
            ->set('dui', '01234567-9') // dígito verificador incorrecto
            ->set('phone_primary', '7555-1234')
            ->call('save')
            ->assertHasErrors('dui');

        Livewire::actingAs($this->admin())->test(Form::class)
            ->set('full_name', 'Pedro Pablo Prueba')
            ->set('dui', '01234567-8') // ya existe (cliente demo)
            ->set('phone_primary', '7555-1234')
            ->call('save')
            ->assertHasErrors('dui');

        Livewire::actingAs($this->admin())->test(Form::class)
            ->set('full_name', 'Pedro Pablo Prueba')
            ->set('dui', '045678911')
            ->set('phone_primary', '7555-1234')
            ->set('delinquency_status_id', (string) $this->statusByCode('MORA_30')->id)
            ->set('days_overdue', 31)
            ->call('save')
            ->assertHasNoErrors()
            ->assertRedirect();

        $customer = Customer::where('dui', '04567891-1')->firstOrFail();
        $this->assertSame('50375551234', $customer->whatsapp_number);
        $this->assertSame('MORA_30', $customer->delinquencyStatus->code);
        $this->assertSame(1, $customer->statusHistories()->count());
        $this->assertSame(StatusChangeSource::Manual, $customer->statusHistories()->first()->source);
    }

    public function test_articles_payments_and_financial_summary(): void
    {
        $admin = $this->admin();
        $customer = Customer::where('dui', '01234567-8')->firstOrFail();
        $this->assertEquals(430.00, (float) $customer->outstanding_balance);

        Livewire::actingAs($admin)->test(Show::class, ['customer' => $customer])
            ->call('createArticle')
            ->set('article.article_code', 'LAP-HP15')
            ->set('article.description', 'Laptop HP 15')
            ->set('article.contract_number', 'CT-TEST-1')
            ->set('article.original_price', '600')
            ->set('article.installments_count', '10')
            ->set('article.installment_amount', '60')
            ->set('article.installments_paid', '2')
            ->call('suggestBalance')
            ->assertSet('article.balance', '480.00')
            ->call('saveArticle')
            ->assertHasNoErrors();

        $customer->refresh();
        $this->assertEquals(910.00, (float) $customer->outstanding_balance);
        $article = CustomerArticle::where('contract_number', 'CT-TEST-1')->first();
        $this->assertSame(8, $article->installments_pending);

        // Pago de 2 cuotas: saldo 360, 4 pagadas, próxima fecha +2 meses
        $article->update(['next_payment_date' => '2026-12-01']);
        app(PaymentService::class)->register($article, 120, now()->toDateString(), PaymentMethod::Cash, 'R-1', null, $admin);
        $article->refresh();
        $this->assertEquals(360.00, (float) $article->balance);
        $this->assertSame(4, $article->installments_paid);
        $this->assertSame('2027-02-01', $article->next_payment_date->toDateString());
        $this->assertEquals(790.00, (float) $customer->fresh()->outstanding_balance);

        // Pago que excede el saldo se rechaza
        Livewire::actingAs($admin)->test(Show::class, ['customer' => $customer])
            ->call('openPayment', $article->id)
            ->set('payment.amount', '9999')
            ->call('savePayment')
            ->assertHasErrors('payment.amount');
    }

    public function test_status_change_creates_immutable_history(): void
    {
        $customer = Customer::where('dui', '01234567-8')->firstOrFail();
        $before = $customer->statusHistories()->count();

        Livewire::actingAs($this->admin())->test(Show::class, ['customer' => $customer])
            ->call('openStatusModal')
            ->set('newStatusId', (string) $this->statusByCode('ULTIMA_GESTION')->id)
            ->set('newDays', 200)
            ->set('statusReason', 'Cliente no responde a gestiones')
            ->call('saveStatus')
            ->assertHasNoErrors();

        $history = $customer->statusHistories()->latest('id')->first();
        $this->assertSame($before + 1, $customer->statusHistories()->count());
        $this->assertSame('Cliente no responde a gestiones', $history->reason);
        $this->assertSame($this->admin()->id, $history->user_id);

        $this->expectException(LogicException::class);
        $history->update(['reason' => 'alterado']);
    }

    public function test_automatic_recalculation_respects_manual_statuses(): void
    {
        $service = app(DelinquencyService::class);
        $this->assertSame('SOLVENTE', $service->statusForDays(0)->code);
        $this->assertSame('SOLVENTE', $service->statusForDays(5)->code);
        $this->assertSame('MORA_10', $service->statusForDays(12)->code);
        $this->assertSame('MORA_60', $service->statusForDays(75)->code);
        $this->assertSame('MORA_CRITICA', $service->statusForDays(400)->code); // "Última gestión" es manual

        $customer = Customer::where('dui', '01234567-8')->firstOrFail();
        $customer->articles()->update(['next_payment_date' => now()->subDays(65)->toDateString()]);
        RecalculateDelinquencyJob::dispatchSync($this->admin()->id);
        $this->assertSame('MORA_60', $customer->fresh()->delinquencyStatus->code);

        $service->changeStatus($customer->fresh(), $this->statusByCode('RECUPERACION'), null, StatusChangeSource::Manual, 'Negociación');
        RecalculateDelinquencyJob::dispatchSync();
        $this->assertSame('RECUPERACION', $customer->fresh()->delinquencyStatus->code);
    }

    public function test_bulk_status_update(): void
    {
        $ids = Customer::active()->limit(3)->pluck('id')->map(fn ($id) => (string) $id)->all();

        Livewire::actingAs($this->admin())->test(Updates::class)
            ->set('selected', $ids)
            ->set('targetStatus', (string) $this->statusByCode('RECUPERACION')->id)
            ->set('reason', 'Traslado a recuperación')
            ->call('apply')
            ->assertHasNoErrors();

        $this->assertSame(3, Customer::whereIn('id', $ids)->where('delinquency_status_id', $this->statusByCode('RECUPERACION')->id)->count());
        $this->assertSame(3, CustomerStatusHistory::where('reason', 'Traslado a recuperación')->count());
    }

    public function test_delinquency_status_crud(): void
    {
        Livewire::actingAs($this->admin())->test(Statuses::class)
            ->call('create')
            ->set('form.name', 'Mora 45 días')
            ->set('form.min_days_overdue', 45)
            ->call('save')
            ->assertHasNoErrors();

        $status = DelinquencyStatus::where('code', 'MORA_45_DIAS')->firstOrFail();
        $this->assertSame('MORA_45_DIAS', app(DelinquencyService::class)->statusForDays(50)->code);

        // No se puede eliminar un estado con clientes
        Livewire::actingAs($this->admin())->test(Statuses::class)->call('delete', $this->statusByCode('SOLVENTE')->id)->assertDispatched('alert');
        $this->assertDatabaseHas('delinquency_statuses', ['code' => 'SOLVENTE']);

        Livewire::actingAs($this->admin())->test(Statuses::class)->call('delete', $status->id);
        $this->assertDatabaseMissing('delinquency_statuses', ['code' => 'MORA_45_DIAS']);
    }

    public function test_excel_import_validates_detects_duplicates_and_imports(): void
    {
        Storage::fake('local');
        $existing = Customer::where('dui', '01234567-8')->firstOrFail();

        $csv = implode("\n", [
            'codigo_cliente,nombre_completo,dui,telefono_principal,correo,estado_mora,dias_mora,acepta_promociones,codigo_articulo,descripcion_articulo,numero_contrato,cantidad_cuotas,valor_cuota,cuotas_pagadas,proxima_fecha_pago',
            'IMP-001,Ana Importada,04567891-1,7123-4567,ana@correo.com,MORA_30,35,si,TV-1,Televisor,CT-900,12,50,4,01/10/2026',
            'IMP-001,Ana Importada,04567891-1,7123-4567,ana@correo.com,MORA_30,35,si,REF-1,Refrigeradora,CT-901,10,40,8,01/10/2026',
            'IMP-001,Ana Importada,04567891-1,7123-4567,ana@correo.com,MORA_30,35,si,REF-1,Refrigeradora,CT-901,10,40,8,01/10/2026',
            "{$existing->code},María Fernanda López Hernández,01234567-8,7012-3456,,SOLVENTE,0,no,,,,,,,",
            'IMP-002,Sin DUI valido,11111111-1,7000-1111,,,,,,,,,,,',
            'IMP-003,Estado inexistente,00000001-8,7000-2222,,NO_EXISTE,,,,,,,,,',
            'IMP-004,Codigo de otro,'.$existing->dui.',7000-3333,,,,,,,,,,,',
        ]);

        $file = UploadedFile::fake()->createWithContent('clientes.csv', $csv);

        $component = Livewire::actingAs($this->admin())->test(Import::class)->set('file', $file);
        $batch = ImportBatch::latest('id')->first();

        $this->assertSame(ImportStatus::Validated, $batch->status);
        $this->assertSame(7, $batch->total_rows);
        $this->assertSame(3, $batch->valid_rows);
        $this->assertSame(4, $batch->error_rows);
        $this->assertSame(1, $batch->duplicate_rows);
        $component->assertSet('analysis.new_customers', 1)->assertSet('analysis.existing_customers', 1);

        $this->actingAs($this->admin())->get(route('customers.import.errors', $batch))->assertOk();

        $component->call('import');
        $batch->refresh();

        $this->assertSame(ImportStatus::Completed, $batch->status);
        $this->assertSame(1, $batch->customers_created);
        $this->assertSame(1, $batch->customers_updated);
        $this->assertSame(2, $batch->articles_upserted);

        $ana = Customer::where('code', 'IMP-001')->firstOrFail();
        $this->assertSame('MORA_30', $ana->delinquencyStatus->code);
        $this->assertSame(35, $ana->days_overdue);
        $this->assertTrue($ana->accepts_promotions);
        $this->assertEquals(8 * 50 + 2 * 40, (float) $ana->outstanding_balance);
        $this->assertSame(StatusChangeSource::Import, $ana->statusHistories()->first()->source);

        $this->assertSame('SOLVENTE', $existing->fresh()->delinquencyStatus->code);
        $this->assertSame(2, $existing->articles()->count(), 'Los artículos existentes no se modifican si la fila no trae artículo');
    }

    public function test_import_template_download(): void
    {
        $this->actingAs($this->admin())->get(route('customers.import.template'))
            ->assertOk()
            ->assertHeader('content-disposition', 'attachment; filename=plantilla_importacion_clientes.xlsx');
    }

    public function test_import_rejects_file_without_required_columns(): void
    {
        Storage::fake('local');
        $file = UploadedFile::fake()->createWithContent('malo.csv', "nombre,telefono\nJuan,70001111");
        Livewire::actingAs($this->admin())->test(Import::class)->set('file', $file);

        $batch = ImportBatch::latest('id')->first();
        $this->assertSame(ImportStatus::Failed, $batch->status);
        $this->assertStringContainsString('codigo_cliente', $batch->failure_message);
    }

    public function test_date_and_number_parsing(): void
    {
        $service = app(CustomerImportService::class);
        $this->assertSame('2026-10-01', $service->parseDate('01/10/2026')->toDateString());
        $this->assertSame('2026-10-01', $service->parseDate('2026-10-01')->toDateString());
        $this->assertSame('2023-11-01', $service->parseDate(45231)->toDateString());
        $this->assertNull($service->parseDate('31/02/2026'));
        $this->assertSame(1250.5, $service->parseNumber('$1,250.50'));
        $this->assertTrue($service->parseBool('Sí'));
    }
}
