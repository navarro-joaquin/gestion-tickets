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
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

final class CloseTicketDay
{
    public function __construct(private readonly QueueNotifier $notifier) {}

    /**
     * Cierra la jornada actual y cualquier jornada anterior que haya quedado abierta.
     *
     * Con `$includeCurrentPeriod` en `false` solo se cierran los periodos anteriores
     * a hoy (tickets, sesiones y asignaciones que no se cerraron a su hora).
     *
     * @return array{expired: int, no_show: int, finished: int}
     */
    public function handle(?CarbonInterface $moment = null, bool $includeCurrentPeriod = true): array
    {
        $now = $moment ? Carbon::instance($moment) : now();
        $periodKey = $now->toDateString();
        $periodOperator = $includeCurrentPeriod ? '<=' : '<';
        $affectedSections = [];
        $expired = 0;
        $noShow = 0;
        $finished = 0;

        DB::transaction(function () use ($now, $periodKey, $periodOperator, $includeCurrentPeriod, &$affectedSections, &$expired, &$noShow, &$finished): void {
            $ticketsToClose = fn (TicketStatus $status): Collection => Ticket::query()
                ->where('status', $status)
                ->where('period_key', $periodOperator, $periodKey)
                ->with(['section:id,slug', 'currentSection:id,slug'])
                ->lockForUpdate()
                ->get();

            foreach ($ticketsToClose(TicketStatus::Waiting) as $ticket) {
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

            foreach ($ticketsToClose(TicketStatus::Called) as $ticket) {
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

            foreach ($ticketsToClose(TicketStatus::InService) as $ticket) {
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

            $startOfDay = $now->copy()->startOfDay();

            OperatorSession::query()
                ->whereNull('ended_at')
                ->unless($includeCurrentPeriod, fn (Builder $query): Builder => $query->where('started_at', '<', $startOfDay))
                ->update(['ended_at' => $now]);

            StationAssignment::query()
                ->whereNull('released_at')
                ->unless($includeCurrentPeriod, fn (Builder $query): Builder => $query->where('assigned_at', '<', $startOfDay))
                ->update(['released_at' => $now]);
        });

        foreach (array_keys($affectedSections) as $slug) {
            $this->notifier->notify($slug);
        }

        return ['expired' => $expired, 'no_show' => $noShow, 'finished' => $finished];
    }
}
