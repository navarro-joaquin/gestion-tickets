<?php

namespace Database\Factories\Domain\Sections;

use App\Domain\Sections\Section;
use App\Domain\Sections\SectionTicketCode;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SectionTicketCode>
 */
class SectionTicketCodeFactory extends Factory
{
    protected $model = SectionTicketCode::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'section_id' => Section::factory(),
            'code' => fake()->unique()->randomLetter(),
            'sort_order' => 0,
            'is_active' => true,
        ];
    }
}
