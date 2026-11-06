<?php

namespace Database\Factories\Domain\Ticketing;

use App\Domain\Sections\Section;
use App\Domain\Ticketing\TicketSequence;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TicketSequence>
 */
class TicketSequenceFactory extends Factory
{
    protected $model = TicketSequence::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'section_id' => Section::factory(),
            'code' => 'A',
            'period_key' => now()->toDateString(),
            'last_number' => 0,
        ];
    }
}
