<?php

namespace App\Domain\Priorities;

use App\Domain\Sections\Section;
use App\Domain\Ticketing\Ticket;
use App\Domain\Ticketing\TicketEvent;
use App\Domain\Ticketing\TicketEventType;
use App\Domain\Ticketing\TicketStatus;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;

final class QueuePrioritySelector
{
    public function __construct(
        private readonly PriorityProfileResolver $resolver,
    ) {}

    public function select(
        Section $section,
        CarbonInterface $moment,
        ?User $operator = null,
    ): ?QueueSelection {
        $profile = $this->resolver->resolve($moment);

        $calls = $this->callsToday($section, $moment);
        $generalCalls = $calls['general'];
        $preferentialCalls = $calls['preferential'];
        $consecutivePreferential = $this->consecutivePreferentialCalls($section, $moment, $profile);

        $base = $this->waitingQuery($section, $operator);

        $oldestGeneral = (clone $base)
            ->where('is_preferential', false)
            ->orderBy('issued_at')
            ->orderBy('id')
            ->lockForUpdate()
            ->first();

        $oldestPreferential = (clone $base)
            ->where('is_preferential', true)
            ->orderBy('issued_at')
            ->orderBy('id')
            ->lockForUpdate()
            ->first();

        if ($oldestGeneral === null && $oldestPreferential === null) {
            return null;
        }

        $generalWaiting = $oldestGeneral !== null;
        $preferentialWaiting = $oldestPreferential !== null;
        $generalWait = $oldestGeneral ? $this->waitMinutes($oldestGeneral, $moment) : 0;
        $preferentialWait = $oldestPreferential ? $this->waitMinutes($oldestPreferential, $moment) : 0;

        if (! $generalWaiting) {
            $selected = $oldestPreferential;
            $group = 'preferential';
            $reason = 'only_preferential';
        } elseif (! $preferentialWaiting) {
            $selected = $oldestGeneral;
            $group = 'general';
            $reason = 'only_general';
        } else {
            $targetPreferential = intdiv(
                ($generalCalls + $preferentialCalls + 1) * $profile->preferential_ratio,
                $profile->totalRatio(),
            );

            $group = $preferentialCalls < $targetPreferential ? 'preferential' : 'general';
            $reason = 'ratio';

            if ($group === 'preferential' && $consecutivePreferential >= $profile->max_consecutive_preferential) {
                $group = 'general';
                $reason = 'max_consecutive_preferential';
            }

            if ($group === 'preferential'
                && $generalWait >= $profile->aging_minutes
                && $generalWait > $preferentialWait
            ) {
                $group = 'general';
                $reason = 'aging_general';
            }

            if ($group === 'general'
                && $preferentialWait >= $profile->aging_minutes
                && $preferentialWait > $generalWait
            ) {
                $group = 'preferential';
                $reason = 'aging_preferential';
            }

            $selected = $group === 'preferential' ? $oldestPreferential : $oldestGeneral;
        }

        $waited = $this->waitMinutes($selected, $moment);
        $score = $selected->is_preferential ? 100000 + $waited : $waited;

        return new QueueSelection($selected, [
            'profile_id' => $profile->getKey(),
            'profile_name' => $profile->name,
            'ratios' => [
                'general' => $profile->general_ratio,
                'preferential' => $profile->preferential_ratio,
            ],
            'calls_today' => [
                'general' => $generalCalls,
                'preferential' => $preferentialCalls,
            ],
            'consecutive_preferential' => $consecutivePreferential,
            'aging_minutes' => $profile->aging_minutes,
            'max_consecutive_preferential' => $profile->max_consecutive_preferential,
            'waiting' => [
                'general' => $generalWaiting,
                'preferential' => $preferentialWaiting,
            ],
            'oldest_wait_minutes' => [
                'general' => $generalWait,
                'preferential' => $preferentialWait,
            ],
            'selected_group' => $group,
            'reason' => $reason,
            'waited_minutes' => $waited,
            'score' => $score,
        ]);
    }

    /**
     * @return array{general: int, preferential: int}
     */
    private function callsToday(Section $section, CarbonInterface $moment): array
    {
        $counts = Ticket::query()
            ->where('current_section_id', $section->id)
            ->whereDate('called_at', $moment->toDateString())
            ->whereIn('status', [
                TicketStatus::Called,
                TicketStatus::InService,
                TicketStatus::Finished,
                TicketStatus::NoShow,
            ])
            ->selectRaw('is_preferential, count(*) as total')
            ->groupBy('is_preferential')
            ->pluck('total', 'is_preferential');

        return [
            'general' => (int) ($counts[0] ?? 0),
            'preferential' => (int) ($counts[1] ?? 0),
        ];
    }

    private function consecutivePreferentialCalls(
        Section $section,
        CarbonInterface $moment,
        PriorityProfile $profile,
    ): int {
        $recent = TicketEvent::query()
            ->where('type', TicketEventType::Called)
            ->whereDate('occurred_at', $moment->toDateString())
            ->whereHas(
                'ticket',
                fn (Builder $query): Builder => $query->where('current_section_id', $section->id),
            )
            ->orderByDesc('id')
            ->limit(max(1, $profile->max_consecutive_preferential + 1))
            ->with('ticket:id,is_preferential')
            ->get()
            ->pluck('ticket.is_preferential');

        $consecutive = 0;

        foreach ($recent as $isPreferential) {
            if (! (bool) $isPreferential) {
                break;
            }

            $consecutive++;
        }

        return $consecutive;
    }

    private function waitingQuery(Section $section, ?User $operator): Builder
    {
        $query = Ticket::query()
            ->waiting()
            ->where('current_section_id', $section->id);

        if ($operator !== null) {
            $authorizedServiceIds = $operator->authorizedServices()->pluck('services.id');

            $query->where(function (Builder $query) use ($authorizedServiceIds): void {
                $query->whereNull('service_id');

                if ($authorizedServiceIds->isNotEmpty()) {
                    $query->orWhereIn('service_id', $authorizedServiceIds);
                }
            });
        }

        return $query;
    }

    private function waitMinutes(Ticket $ticket, CarbonInterface $moment): int
    {
        return (int) abs($ticket->issued_at->diffInMinutes($moment));
    }
}
