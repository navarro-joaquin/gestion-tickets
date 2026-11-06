<?php

namespace App\Domain\Operations\Actions;

use App\Domain\Ticketing\Ticket;
use App\Domain\Ticketing\TicketEvent;
use App\Domain\Ticketing\TicketEventType;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;

final class RegisterPrintFailure
{
    public function handle(
        Ticket $ticket,
        ?string $message = null,
        ?int $actorId = null,
        ?CarbonInterface $moment = null,
    ): Ticket {
        $now = $moment ? Carbon::instance($moment) : now();

        DB::transaction(function () use ($ticket, $message, $actorId, $now): void {
            Ticket::query()->lockForUpdate()->findOrFail($ticket->id);

            TicketEvent::query()->create([
                'ticket_id' => $ticket->id,
                'type' => TicketEventType::PrintFailed,
                'actor_id' => $actorId,
                'occurred_at' => $now,
                'payload' => [
                    'label' => $ticket->label,
                    'message' => $message,
                ],
            ]);
        });

        return $ticket->fresh(['section', 'currentSection', 'category']);
    }
}
