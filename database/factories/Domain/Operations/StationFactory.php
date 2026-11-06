<?php

namespace Database\Factories\Domain\Operations;

use App\Domain\Operations\Station;
use App\Domain\Sections\Section;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Station>
 */
class StationFactory extends Factory
{
    protected $model = Station::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'section_id' => Section::factory(),
            'name' => 'Ventanilla '.fake()->unique()->numberBetween(1, 99),
            'is_active' => true,
            'sort_order' => 0,
        ];
    }
}
