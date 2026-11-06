<?php

namespace App\Domain\Priorities;

use App\Domain\Ticketing\Ticket;

final readonly class QueueSelection
{
    /**
     * @param  array<string, mixed>  $snapshot
     */
    public function __construct(
        public Ticket $ticket,
        public array $snapshot,
    ) {}
}
