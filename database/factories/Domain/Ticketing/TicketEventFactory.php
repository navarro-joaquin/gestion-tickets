<?php

namespace Database\Factories\Domain\Ticketing;

use App\Domain\Ticketing\Ticket;
use App\Domain\Ticketing\TicketEvent;
use App\Domain\Ticketing\TicketEventType;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TicketEvent>
 */
class TicketEventFactory extends Factory
{
    protected $model = TicketEvent::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'ticket_id' => Ticket::factory(),
            'type' => TicketEventType::Issued,
            'actor_id' => null,
            'payload' => [],
            'occurred_at' => now(),
        ];
    }
}
