<?php

namespace Database\Factories\Domain\Services;

use App\Domain\Sections\Section;
use App\Domain\Services\Service;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Service>
 */
class ServiceFactory extends Factory
{
    protected $model = Service::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->unique()->words(2, true);

        return [
            'section_id' => Section::factory(),
            'slug' => Str::slug($name),
            'name' => Str::title($name),
            'label' => strtoupper(fake()->lexify('SRV???')),
            'description' => null,
            'is_active' => true,
            'available_days' => null,
            'available_from' => null,
            'available_to' => null,
            'effective_from' => null,
            'effective_to' => null,
            'sort_order' => 0,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn (): array => [
            'is_active' => false,
        ]);
    }

    /**
     * @param  list<int>  $days
     */
    public function schedule(array $days, string $from, string $to): static
    {
        return $this->state(fn (): array => [
            'available_days' => $days,
            'available_from' => $from,
            'available_to' => $to,
        ]);
    }
}
