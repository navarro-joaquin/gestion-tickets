<?php

namespace Database\Seeders;

use App\Domain\Priorities\PriorityProfile;
use Illuminate\Database\Seeder;

class PriorityProfileSeeder extends Seeder
{
    public function run(): void
    {
        PriorityProfile::query()->updateOrCreate(
            ['name' => 'Estándar 2:1'],
            [
                'description' => 'Dos tickets generales por cada ticket preferencial, con aging de 30 minutos.',
                'general_ratio' => 2,
                'preferential_ratio' => 1,
                'aging_minutes' => 30,
                'max_consecutive_preferential' => 1,
                'is_active' => true,
                'effective_from' => null,
                'effective_to' => null,
            ],
        );
    }
}
