<?php

namespace Tests;

use App\Models\User;
use App\Services\Settings;
use App\Support\Permissions;
use Database\Seeders\DemoCustomerSeeder;
use Database\Seeders\ProductionSeeder;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    /** Carga datos base (roles, usuarios, estados, plantillas, chatbot) y opcionalmente clientes demo. */
    protected function seedBase(int $demoCustomers = 0): void
    {
        $this->seed(ProductionSeeder::class);
        app(Settings::class)->flush();

        if ($demoCustomers > 0) {
            app(DemoCustomerSeeder::class)->run($demoCustomers);
        }
    }

    protected function userWithRole(string $role = Permissions::ROLE_ADMIN): User
    {
        $user = User::factory()->create(['is_active' => true]);
        $user->assignRole($role);

        return $user;
    }

    protected function admin(): User
    {
        return User::where('email', 'admin@crm.test')->firstOrFail();
    }
}
