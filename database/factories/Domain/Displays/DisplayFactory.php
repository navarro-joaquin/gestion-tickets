<?php

namespace Database\Factories\Domain\Displays;

use App\Domain\Displays\Display;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Display>
 */
class DisplayFactory extends Factory
{
    protected $model = Display::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->words(2, true),
            'slug' => fake()->unique()->slug(2),
            'token' => Str::random(48),
            'layout' => Display::LAYOUT_MIXED,
            'is_active' => true,
            'last_seen_at' => null,
        ];
    }
}
