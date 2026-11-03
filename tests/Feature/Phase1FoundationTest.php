<?php

namespace Tests\Feature;

use App\Livewire\Auth\Login;
use App\Livewire\Dashboard;
use App\Livewire\Profile;
use App\Models\Customer;
use App\Models\User;
use App\Services\DashboardMetrics;
use App\Support\Permissions;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class Phase1FoundationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedBase(demoCustomers: 25);
    }

    public function test_guests_are_redirected_to_login(): void
    {
        $this->get('/')->assertRedirect(route('login'));
        $this->get(route('login'))->assertOk()->assertSee('Iniciar sesión');
    }

    public function test_user_can_login_with_valid_credentials(): void
    {
        Livewire::test(Login::class)
            ->set('email', 'admin@crm.test')
            ->set('password', 'password')
            ->call('login')
            ->assertHasNoErrors()
            ->assertRedirect(route('dashboard'));

        $this->assertAuthenticatedAs($this->admin());
        $this->assertNotNull($this->admin()->last_login_at);
    }

    public function test_login_fails_with_wrong_password(): void
    {
        Livewire::test(Login::class)
            ->set('email', 'admin@crm.test')
            ->set('password', 'incorrecta')
            ->call('login')
            ->assertHasErrors('email');

        $this->assertGuest();
    }

    public function test_inactive_user_cannot_login_and_is_logged_out(): void
    {
        $user = $this->userWithRole(Permissions::ROLE_AGENT);
        $user->update(['is_active' => false]);

        Livewire::test(Login::class)
            ->set('email', $user->email)
            ->set('password', 'password')
            ->call('login')
            ->assertHasErrors('email');

        $this->actingAs($user)->get('/')->assertRedirect(route('login'));
    }

    public function test_dashboard_renders_with_real_metrics(): void
    {
        $this->actingAs($this->admin())
            ->get('/')
            ->assertOk()
            ->assertSee('Total de clientes')
            ->assertSee('Distribución por estado de mora')
            ->assertSee('Modo simulación');

        $kpis = app(DashboardMetrics::class)->kpis();
        $this->assertSame(Customer::active()->count(), $kpis['customers_total']);
        $this->assertSame(
            $kpis['customers_total'],
            $kpis['customers_solvent'] + $kpis['customers_delinquent'] + Customer::active()->whereNull('delinquency_status_id')->count()
        );
        $this->assertEqualsWithDelta(
            (float) Customer::active()->sum('outstanding_balance'),
            $kpis['outstanding_balance'],
            0.01
        );

        Livewire::actingAs($this->admin())->test(Dashboard::class)->call('refresh')->assertDispatched('toast');
    }

    public function test_navigation_respects_permissions(): void
    {
        $agent = User::where('email', 'asesor@crm.test')->first();

        $this->actingAs($agent)->get('/')
            ->assertOk()
            ->assertDontSee('Usuarios y roles')
            ->assertDontSee('Estados de mora');

        $this->assertFalse($agent->can(Permissions::USERS_MANAGE));
        $this->assertTrue($agent->can(Permissions::CONVERSATIONS_ATTEND));
    }

    public function test_profile_and_notifications_pages(): void
    {
        $admin = $this->admin();
        $this->actingAs($admin)->get(route('profile'))->assertOk()->assertSee('Rol y permisos');
        $this->actingAs($admin)->get(route('notifications.index'))->assertOk()->assertSee('Centro de notificaciones');

        Livewire::actingAs($admin)->test(Profile::class)
            ->set('name', 'Admin Actualizado')
            ->call('saveProfile')
            ->assertHasNoErrors();

        $this->assertSame('Admin Actualizado', $admin->fresh()->name);
    }

    public function test_logout(): void
    {
        $this->actingAs($this->admin())->post(route('logout'))->assertRedirect(route('login'));
        $this->assertGuest();
    }
}
