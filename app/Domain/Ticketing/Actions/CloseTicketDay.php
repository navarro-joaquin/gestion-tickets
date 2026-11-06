<?php

namespace App\Domain\Ticketing\Actions;

use App\Domain\Operations\OperatorSession;
use App\Domain\Operations\StationAssignment;
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
     * @return array{expired: int, no_show: int, finished: int}
     */
    public function handle(?CarbonInterface $moment = null): array
    {
        $now = $moment ? Carbon::instance($moment) : now();
        $periodKey = $now->toDateString();
        $affectedSections = [];
        $expired = 0;
        $noShow = 0;
        $finished = 0;

        DB::transaction(function () use ($now, $periodKey, &$affectedSections, &$expired, &$noShow, &$finished): void {
            $waitingTickets = Ticket::query()
                ->where('status', TicketStatus::Waiting)
                ->where('period_key', $periodKey)
                ->with(['section:id,slug', 'currentSection:id,slug'])
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

                $affectedSections[$ticket->currentSection?->slug ?? $ticket->section->slug] = true;
                $expired++;
            }

            $calledTickets = Ticket::query()
                ->where('status', TicketStatus::Called)
                ->where('period_key', $periodKey)
                ->with(['section:id,slug', 'currentSection:id,slug'])
                ->lockForUpdate()
                ->get();

            foreach ($calledTickets as $ticket) {
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
                        'reason' => 'cierre de jornada',
                        'call_count' => $ticket->call_count,
                    ],
                ]);

                $affectedSections[$ticket->currentSection?->slug ?? $ticket->section->slug] = true;
                $noShow++;
            }

            $inServiceTickets = Ticket::query()
                ->where('status', TicketStatus::InService)
                ->where('period_key', $periodKey)
                ->with(['section:id,slug', 'currentSection:id,slug'])
                ->lockForUpdate()
                ->get();

            foreach ($inServiceTickets as $ticket) {
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

                $affectedSections[$ticket->currentSection?->slug ?? $ticket->section->slug] = true;
                $finished++;
            }

            OperatorSession::query()
                ->whereNull('ended_at')
                ->update(['ended_at' => $now]);

            StationAssignment::query()
                ->whereNull('released_at')
                ->update(['released_at' => $now]);
        });

        foreach (array_keys($affectedSections) as $slug) {
            $this->notifier->notify($slug);
        }

        return ['expired' => $expired, 'no_show' => $noShow, 'finished' => $finished];
    }
}
