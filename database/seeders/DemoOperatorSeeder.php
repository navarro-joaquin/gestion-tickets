<?php

namespace Database\Seeders;

use App\Domain\Users\UserRole;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * Usuario de conveniencia para el entorno local.
 *
 * No se ejecuta en producción: DatabaseSeeder lo invoca solo cuando
 * APP_ENV=local. La autenticación real llega en la Fase 4.
 */
class DemoOperatorSeeder extends Seeder
{
    public function run(): void
    {
        User::query()->updateOrCreate(
            ['email' => 'operador@demo.local'],
            [
                'name' => 'Operador de demostración',
                'password' => Hash::make('password'),
                'role' => UserRole::Operator,
                'is_active' => true,
            ],
        );
    }
}
