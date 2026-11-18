<?php

namespace App\Console\Commands;

use App\Domain\Settings\NoShowPolicy;
use App\Domain\Ticketing\Actions\MarkTicketNoShow;
use App\Domain\Ticketing\Ticket;
use App\Domain\Ticketing\TicketStatus;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Validation\ValidationException;

#[Signature('tickets:no-show')]
#[Description('Cierra como no presentados los tickets llamados las veces configuradas cuando pasan los minutos configurados desde el último llamado')]
class ExpireNoShowTickets extends Command
{
    public function handle(MarkTicketNoShow $action, NoShowPolicy $policy): int
    {
        $tickets = Ticket::query()
            ->where('status', TicketStatus::Called)
            ->where('call_count', '>=', $policy->calls())
            ->where('called_at', '<=', $policy->calledBefore(now()))
            ->get();

        $closed = 0;
        $skipped = 0;

        foreach ($tickets as $ticket) {
            try {
                $action->handle($ticket);
                $closed++;
            } catch (ValidationException) {
                $skipped++;
            }
        }

        $this->components->info("Tickets cerrados como no presentados: {$closed}. Omitidos por cambio de estado: {$skipped}.");

        return self::SUCCESS;
    }
}
