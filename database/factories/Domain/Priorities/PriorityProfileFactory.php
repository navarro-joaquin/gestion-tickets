<?php

namespace Database\Factories\Domain\Priorities;

use App\Domain\Priorities\PriorityProfile;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PriorityProfile>
 */
class PriorityProfileFactory extends Factory
{
    protected $model = PriorityProfile::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => 'Perfil '.fake()->unique()->numerify('####'),
            'description' => null,
            'general_ratio' => 2,
            'preferential_ratio' => 1,
            'aging_minutes' => 30,
            'max_consecutive_preferential' => 1,
            'is_active' => true,
            'effective_from' => null,
            'effective_to' => null,
        ];
    }

    public function ratios(int $general, int $preferential): static
    {
        return $this->state(fn (): array => [
            'general_ratio' => $general,
            'preferential_ratio' => $preferential,
        ]);
    }

    public function inactive(): static
    {
        return $this->state(fn (): array => [
            'is_active' => false,
        ]);
    }
}
