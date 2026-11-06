<?php

namespace App\Console\Commands;

use App\Domain\Ticketing\Actions\MarkTicketNoShow;
use App\Domain\Ticketing\Ticket;
use App\Domain\Ticketing\TicketStatus;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('tickets:no-show')]
#[Description('Cierra como no presentados los tickets llamados dos veces con más de un minuto desde el último llamado')]
class ExpireNoShowTickets extends Command
{
    public function handle(MarkTicketNoShow $action): int
    {
        $threshold = now()->subMinute();

        $tickets = Ticket::query()
            ->where('status', TicketStatus::Called)
            ->where('call_count', '>=', 2)
            ->where('called_at', '<=', $threshold)
            ->get();

        foreach ($tickets as $ticket) {
            $action->handle($ticket);
        }

        $this->components->info("Tickets cerrados como no presentados: {$tickets->count()}.");

        return self::SUCCESS;
    }
}
