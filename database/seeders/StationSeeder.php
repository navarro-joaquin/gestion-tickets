<?php

namespace Database\Seeders;

use App\Domain\Operations\Station;
use App\Domain\Sections\Section;
use Illuminate\Database\Seeder;

class StationSeeder extends Seeder
{
    public function run(): void
    {
        $stations = [
            ['name' => 'Ventanilla 1', 'sort_order' => 1],
            ['name' => 'Ventanilla 2', 'sort_order' => 2],
            ['name' => 'Ventanilla 3', 'sort_order' => 3],
        ];

        foreach (Section::query()->orderBy('sort_order')->get() as $section) {
            foreach ($stations as $station) {
                Station::query()->updateOrCreate(
                    [
                        'section_id' => $section->id,
                        'name' => $station['name'],
                    ],
                    [
                        'is_active' => true,
                        'sort_order' => $station['sort_order'],
                    ],
                );
            }
        }
    }
}
