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

final class MarkTicketNoShow
{
    public function __construct(private readonly QueueNotifier $notifier) {}

    public function handle(Ticket $ticket, ?CarbonInterface $moment = null): Ticket
    {
        $now = $moment ? Carbon::instance($moment) : now();

        $ticket = DB::transaction(function () use ($ticket, $now): Ticket {
            $ticket = Ticket::query()->lockForUpdate()->findOrFail($ticket->id);

            if ($ticket->status !== TicketStatus::Called) {
                throw ValidationException::withMessages([
                    'ticket' => ['Solo se puede marcar como no presentado un ticket llamado.'],
                ]);
            }

            $ticket->update([
                'status' => TicketStatus::NoShow,
                'closed_at' => $now,
            ]);

            $ticket->assignments()
                ->whereNull('released_at')
                ->update(['released_at' => $now]);

            TicketEvent::query()->create([
                'ticket_id' => $ticket->id,
                'type' => TicketEventType::NoShow,
                'occurred_at' => $now,
                'payload' => [
                    'station' => $ticket->station_label,
                    'call_count' => $ticket->call_count,
                ],
            ]);

            return $ticket;
        });

        $this->notifier->notify($ticket->currentSection?->slug ?? $ticket->section->slug);

        return $ticket->load(['section', 'currentSection', 'category', 'operator']);
    }
}
