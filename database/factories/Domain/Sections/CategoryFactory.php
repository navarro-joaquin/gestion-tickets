<?php

namespace Database\Factories\Domain\Sections;

use App\Domain\Sections\Category;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Category>
 */
class CategoryFactory extends Factory
{
    protected $model = Category::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->unique()->words(2, true);

        return [
            'slug' => Str::slug($name),
            'name' => Str::title($name),
            'is_preferential' => false,
            'sort_order' => 0,
        ];
    }

    public function general(): static
    {
        return $this->state(fn (): array => [
            'slug' => 'general',
            'name' => 'Atención general',
            'is_preferential' => false,
            'sort_order' => 1,
        ]);
    }

    public function pregnancy(): static
    {
        return $this->state(fn (): array => [
            'slug' => 'pregnancy',
            'name' => 'Mujeres embarazadas',
            'is_preferential' => true,
            'sort_order' => 2,
        ]);
    }

    public function elderly(): static
    {
        return $this->state(fn (): array => [
            'slug' => 'elderly',
            'name' => 'Tercera edad',
            'is_preferential' => true,
            'sort_order' => 3,
        ]);
    }

    public function disability(): static
    {
        return $this->state(fn (): array => [
            'slug' => 'disability',
            'name' => 'Discapacidad',
            'is_preferential' => true,
            'sort_order' => 4,
        ]);
    }
}
