<?php

namespace Database\Factories\Domain\Sections;

use App\Domain\Sections\Section;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Section>
 */
class SectionFactory extends Factory
{
    protected $model = Section::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->unique()->words(2, true);

        return [
            'slug' => Str::slug($name),
            'name' => Str::title($name),
            'is_active' => true,
            'sort_order' => 0,
            'matrix_confirmed_at' => null,
            'matrix_confirmed_by' => null,
        ];
    }

    public function confirmed(): static
    {
        return $this->state(fn (): array => [
            'matrix_confirmed_at' => now(),
            'matrix_confirmed_by' => 'Prueba',
        ]);
    }

    public function inactive(): static
    {
        return $this->state(fn (): array => [
            'is_active' => false,
        ]);
    }
}
