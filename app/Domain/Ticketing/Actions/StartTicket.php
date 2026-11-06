<?php

namespace App\Domain\Ticketing\Actions;

use App\Domain\Ticketing\QueueNotifier;
use App\Domain\Ticketing\Ticket;
use App\Domain\Ticketing\TicketEvent;
use App\Domain\Ticketing\TicketEventType;
use App\Domain\Ticketing\TicketStatus;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class StartTicket
{
    public function __construct(private readonly QueueNotifier $notifier) {}

    public function handle(Ticket $ticket, ?CarbonInterface $moment = null): Ticket
    {
        $now = $moment ? Carbon::instance($moment) : now();

        $ticket = DB::transaction(function () use ($ticket, $now): Ticket {
            $ticket = Ticket::query()->lockForUpdate()->findOrFail($ticket->id);

            if (! $ticket->status->canTransitionTo(TicketStatus::InService)) {
                throw ValidationException::withMessages([
                    'ticket' => ['El ticket debe estar llamado para iniciar la atención.'],
                ]);
            }

            $ticket->update([
                'status' => TicketStatus::InService,
                'started_at' => $now,
            ]);

            TicketEvent::query()->create([
                'ticket_id' => $ticket->id,
                'type' => TicketEventType::Started,
                'occurred_at' => $now,
                'payload' => ['station' => $ticket->station_label],
            ]);

            return $ticket;
        });

        $this->notifier->notify($ticket->section->slug);

        return $ticket->load(['section', 'category', 'operator']);
    }
}
