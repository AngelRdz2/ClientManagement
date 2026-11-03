<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

/**
 * Datos base necesarios para operar (sin datos de demostración).
 */
class ProductionSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            RolesAndPermissionsSeeder::class,
            UserSeeder::class,
            MessageTemplateSeeder::class,
            DelinquencyStatusSeeder::class,
            ChatbotFlowSeeder::class,
        ]);
    }
}
