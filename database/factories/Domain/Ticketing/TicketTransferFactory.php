<?php

namespace Database\Factories\Domain\Ticketing;

use App\Domain\Sections\Section;
use App\Domain\Ticketing\Ticket;
use App\Domain\Ticketing\TicketTransfer;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TicketTransfer>
 */
class TicketTransferFactory extends Factory
{
    protected $model = TicketTransfer::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'ticket_id' => Ticket::factory(),
            'from_section_id' => Section::factory(),
            'to_section_id' => Section::factory(),
            'from_operator_id' => null,
            'to_operator_id' => null,
            'reason' => null,
            'transferred_at' => now(),
        ];
    }
}
