<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Datos base (roles, usuarios, plantillas, estados de mora y flujos del chatbot) y datos de demostración.
     * En producción ejecutar solo: php artisan db:seed --class=ProductionSeeder
     */
    public function run(): void
    {
        $this->call(ProductionSeeder::class);

        if (! app()->isProduction()) {
            $this->call(DemoCustomerSeeder::class);
        }
    }
}
