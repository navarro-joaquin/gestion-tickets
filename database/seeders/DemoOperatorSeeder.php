<?php

namespace Database\Seeders;

use App\Domain\Users\UserRole;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * Usuarios de conveniencia para el entorno local.
 *
 * No se ejecuta en producción: DatabaseSeeder lo invoca solo cuando
 * APP_ENV=local. El administrador permite entrar a /admin mientras la
 * gestión de usuarios siga pendiente (Fase 5).
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

        User::query()->updateOrCreate(
            ['email' => 'admin@demo.local'],
            [
                'name' => 'Administrador de demostración',
                'password' => Hash::make('password'),
                'role' => UserRole::Admin,
                'is_active' => true,
            ],
        );
    }
}
