<?php

namespace App\Domain\Reports;

use App\Domain\Ticketing\Ticket;
use App\Domain\Ticketing\TicketStatus;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

final class TicketReport
{
    /**
     * @return array<string, mixed>
     */
    public function summary(ReportFilters $filters): array
    {
        $base = $filters->apply(Ticket::query());

        $statusCounts = (clone $base)
            ->select('status', DB::raw('COUNT(*) as total'))
            ->groupBy('status')
            ->pluck('total', 'status');

        $totals = ['issued' => 0];

        foreach (TicketStatus::cases() as $status) {
            $count = (int) ($statusCounts[$status->value] ?? 0);
            $totals[$status->value] = $count;
            $totals['issued'] += $count;
        }

        $averages = (clone $base)
            ->selectRaw(
                'AVG(TIMESTAMPDIFF(SECOND, issued_at, called_at)) as avg_wait_seconds,'
                .' AVG(TIMESTAMPDIFF(SECOND, started_at, finished_at)) as avg_service_seconds,'
                .' AVG(TIMESTAMPDIFF(SECOND, issued_at, closed_at)) as avg_total_seconds',
            )
            ->first();

        return [
            'period' => $filters->period(),
            'filters' => $filters->toArray(),
            'totals' => [
                ...$totals,
                'avg_wait_seconds' => $this->seconds($averages?->avg_wait_seconds),
                'avg_service_seconds' => $this->seconds($averages?->avg_service_seconds),
                'avg_total_seconds' => $this->seconds($averages?->avg_total_seconds),
            ],
            'by_section' => $this->bySection($base),
            'by_category' => $this->byCategory($base),
            'by_service' => $this->byService($base),
            'by_station' => $this->byStation($base),
        ];
    }

    public function tickets(ReportFilters $filters, int $perPage = 25): LengthAwarePaginator
    {
        return $filters->apply(Ticket::query())
            ->with($this->relations())
            ->orderByDesc('issued_at')
            ->orderByDesc('id')
            ->paginate($perPage);
    }

    /**
     * @return Builder<Ticket>
     */
    public function query(ReportFilters $filters): Builder
    {
        return $filters->apply(Ticket::query())
            ->with($this->relations())
            ->orderBy('id');
    }

    /**
     * @return list<string>
     */
    public function relations(): array
    {
        return [
            'section:id,slug,name',
            'currentSection:id,slug,name',
            'category:id,slug,name,is_preferential',
            'service:id,slug,name,label',
            'operator:id,name',
        ];
    }

    /**
     * @param  Builder<Ticket>  $base
     * @return list<array<string, mixed>>
     */
    private function bySection(Builder $base): array
    {
        return (clone $base)
            ->join('sections', 'sections.id', '=', 'tickets.section_id')
            ->select('sections.id as section_id', 'sections.slug', 'sections.name')
            ->selectRaw($this->aggregateSelect())
            ->groupBy('sections.id', 'sections.slug', 'sections.name', 'sections.sort_order')
            ->orderBy('sections.sort_order')
            ->orderBy('sections.name')
            ->get()
            ->map(fn (object $row): array => [
                'section' => [
                    'id' => (int) $row->section_id,
                    'slug' => $row->slug,
                    'name' => $row->name,
                ],
                ...$this->normalize($row),
            ])
            ->all();
    }

    /**
     * @param  Builder<Ticket>  $base
     * @return list<array<string, mixed>>
     */
    private function byCategory(Builder $base): array
    {
        return (clone $base)
            ->join('categories', 'categories.id', '=', 'tickets.category_id')
            ->select(
                'categories.id as category_id',
                'categories.slug',
                'categories.name',
                'categories.is_preferential',
            )
            ->selectRaw($this->aggregateSelect())
            ->groupBy(
                'categories.id',
                'categories.slug',
                'categories.name',
                'categories.is_preferential',
                'categories.sort_order',
            )
            ->orderBy('categories.sort_order')
            ->orderBy('categories.name')
            ->get()
            ->map(fn (object $row): array => [
                'category' => [
                    'id' => (int) $row->category_id,
                    'slug' => $row->slug,
                    'name' => $row->name,
                    'is_preferential' => (bool) $row->is_preferential,
                ],
                ...$this->normalize($row),
            ])
            ->all();
    }

    /**
     * @param  Builder<Ticket>  $base
     * @return list<array<string, mixed>>
     */
    private function byService(Builder $base): array
    {
        return (clone $base)
            ->leftJoin('services', 'services.id', '=', 'tickets.service_id')
            ->select('tickets.service_id', 'services.slug', 'services.name')
            ->selectRaw('MAX(tickets.service_label) as service_label')
            ->selectRaw($this->aggregateSelect())
            ->groupBy('tickets.service_id', 'services.slug', 'services.name')
            ->orderBy('services.name')
            ->get()
            ->map(fn (object $row): array => [
                'service' => [
                    'id' => $row->service_id === null ? null : (int) $row->service_id,
                    'slug' => $row->slug,
                    'name' => $row->name,
                    'label' => $row->service_label,
                ],
                ...$this->normalize($row),
            ])
            ->all();
    }

    /**
     * @param  Builder<Ticket>  $base
     * @return list<array<string, mixed>>
     */
    private function byStation(Builder $base): array
    {
        return (clone $base)
            ->select('tickets.station_label')
            ->selectRaw($this->aggregateSelect())
            ->groupBy('tickets.station_label')
            ->orderBy('tickets.station_label')
            ->get()
            ->map(fn (object $row): array => [
                'station_label' => $row->station_label,
                ...$this->normalize($row),
            ])
            ->all();
    }

    private function aggregateSelect(): string
    {
        return 'COUNT(*) as issued,'
            ." SUM(tickets.status = 'finished') as finished,"
            ." SUM(tickets.status = 'no_show') as no_show,"
            ." SUM(tickets.status = 'expired') as expired,"
            ." SUM(tickets.status = 'cancelled') as cancelled,"
            .' AVG(TIMESTAMPDIFF(SECOND, tickets.issued_at, tickets.called_at)) as avg_wait_seconds,'
            .' AVG(TIMESTAMPDIFF(SECOND, tickets.started_at, tickets.finished_at)) as avg_service_seconds';
    }

    /**
     * @return array<string, int|null>
     */
    private function normalize(object $row): array
    {
        return [
            'issued' => (int) $row->issued,
            'finished' => (int) $row->finished,
            'no_show' => (int) $row->no_show,
            'expired' => (int) $row->expired,
            'cancelled' => (int) $row->cancelled,
            'avg_wait_seconds' => $this->seconds($row->avg_wait_seconds),
            'avg_service_seconds' => $this->seconds($row->avg_service_seconds),
        ];
    }

    private function seconds(mixed $value): ?int
    {
        return $value === null ? null : (int) round((float) $value);
    }
}
