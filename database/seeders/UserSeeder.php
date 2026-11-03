<?php

namespace Database\Seeders;

use App\Models\User;
use App\Support\Permissions;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        $users = [
            ['Administrador General', 'admin@crm.test', 'Gerente de sistemas', Permissions::ROLE_ADMIN],
            ['Laura Martínez', 'supervisor@crm.test', 'Supervisora de cobranza', Permissions::ROLE_SUPERVISOR],
            ['Carlos Hernández', 'asesor@crm.test', 'Asesor de atención', Permissions::ROLE_AGENT],
            ['Daniela Rivas', 'asesor2@crm.test', 'Asesora de atención', Permissions::ROLE_AGENT],
            ['José Ramírez', 'cobranza@crm.test', 'Operador de cobranza', Permissions::ROLE_COLLECTOR],
        ];

        foreach ($users as [$name, $email, $position, $role]) {
            $user = User::updateOrCreate(['email' => $email], [
                'name' => $name,
                'position' => $position,
                'password' => Hash::make('password'),
                'is_active' => true,
                'email_verified_at' => now(),
            ]);
            $user->syncRoles([$role]);
        }
    }
}
