<?php

namespace Database\Factories\Domain\Ticketing;

use App\Domain\Ticketing\AssignmentReason;
use App\Domain\Ticketing\Ticket;
use App\Domain\Ticketing\TicketAssignment;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TicketAssignment>
 */
class TicketAssignmentFactory extends Factory
{
    protected $model = TicketAssignment::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'ticket_id' => Ticket::factory(),
            'operator_id' => null,
            'station_label' => 'Ventanilla 1',
            'reason' => AssignmentReason::Called,
            'assigned_at' => now(),
            'released_at' => null,
        ];
    }
}
