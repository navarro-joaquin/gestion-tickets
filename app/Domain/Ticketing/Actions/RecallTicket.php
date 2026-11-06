<?php

namespace App\Domain\Ticketing\Actions;

use App\Domain\Ticketing\AssignmentReason;
use App\Domain\Ticketing\QueueNotifier;
use App\Domain\Ticketing\Ticket;
use App\Domain\Ticketing\TicketAssignment;
use App\Domain\Ticketing\TicketEvent;
use App\Domain\Ticketing\TicketEventType;
use App\Domain\Ticketing\TicketStatus;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class RecallTicket
{
    public function __construct(private readonly QueueNotifier $notifier) {}

    public function handle(Ticket $ticket, ?CarbonInterface $moment = null): Ticket
    {
        $now = $moment ? Carbon::instance($moment) : now();

        $ticket = DB::transaction(function () use ($ticket, $now): Ticket {
            $ticket = Ticket::query()->lockForUpdate()->findOrFail($ticket->id);

            if ($ticket->status !== TicketStatus::Called) {
                throw ValidationException::withMessages([
                    'ticket' => ['Solo se puede rellamar un ticket que está en estado llamado.'],
                ]);
            }

            if ($ticket->call_count >= 2) {
                throw ValidationException::withMessages([
                    'ticket' => ['El ticket ya alcanzó el máximo de 2 llamados; se cerrará como no presentado.'],
                ]);
            }

            $ticket->assignments()
                ->whereNull('released_at')
                ->update(['released_at' => $now]);

            $ticket->update([
                'called_at' => $now,
                'call_count' => $ticket->call_count + 1,
            ]);

            TicketAssignment::query()->create([
                'ticket_id' => $ticket->id,
                'operator_id' => $ticket->operator_id,
                'station_label' => $ticket->station_label,
                'reason' => AssignmentReason::Recalled,
                'assigned_at' => $now,
            ]);

            TicketEvent::query()->create([
                'ticket_id' => $ticket->id,
                'type' => TicketEventType::Recalled,
                'occurred_at' => $now,
                'payload' => [
                    'station' => $ticket->station_label,
                    'call_count' => $ticket->call_count,
                ],
            ]);

            return $ticket;
        });

        $this->notifier->notify($ticket->section->slug);

        return $ticket->load(['section', 'category', 'operator']);
    }
}
