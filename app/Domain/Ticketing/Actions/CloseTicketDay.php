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

final class CloseTicketDay
{
    public function __construct(private readonly QueueNotifier $notifier) {}

    /**
     * @return array{expired: int, finished: int}
     */
    public function handle(?CarbonInterface $moment = null): array
    {
        $now = $moment ? Carbon::instance($moment) : now();
        $periodKey = $now->toDateString();
        $affectedSections = [];
        $expired = 0;
        $finished = 0;

        DB::transaction(function () use ($now, $periodKey, &$affectedSections, &$expired, &$finished): void {
            $waitingTickets = Ticket::query()
                ->where('status', TicketStatus::Waiting)
                ->where('period_key', $periodKey)
                ->lockForUpdate()
                ->get();

            foreach ($waitingTickets as $ticket) {
                $ticket->update([
                    'status' => TicketStatus::Expired,
                    'closed_at' => $now,
                ]);

                TicketEvent::query()->create([
                    'ticket_id' => $ticket->id,
                    'type' => TicketEventType::Expired,
                    'occurred_at' => $now,
                    'payload' => ['reason' => 'cierre de jornada'],
                ]);

                $affectedSections[$ticket->section->slug] = true;
                $expired++;
            }

            $activeTickets = Ticket::query()
                ->whereIn('status', [TicketStatus::Called, TicketStatus::InService])
                ->where('period_key', $periodKey)
                ->lockForUpdate()
                ->get();

            foreach ($activeTickets as $ticket) {
                $ticket->update([
                    'status' => TicketStatus::Finished,
                    'finished_at' => $now,
                    'closed_at' => $now,
                ]);

                $ticket->assignments()
                    ->whereNull('released_at')
                    ->update(['released_at' => $now]);

                TicketEvent::query()->create([
                    'ticket_id' => $ticket->id,
                    'type' => TicketEventType::Finished,
                    'occurred_at' => $now,
                    'payload' => ['reason' => 'cierre de jornada'],
                ]);

                $affectedSections[$ticket->section->slug] = true;
                $finished++;
            }
        });

        foreach (array_keys($affectedSections) as $slug) {
            $this->notifier->notify($slug);
        }

        return ['expired' => $expired, 'finished' => $finished];
    }
}
