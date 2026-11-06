<?php

namespace App\Domain\Reports;

use App\Domain\Ticketing\Ticket;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;

final readonly class ReportFilters
{
    public function __construct(
        public CarbonInterface $from,
        public CarbonInterface $to,
        public ?string $section = null,
        public ?string $category = null,
        public ?string $service = null,
        public ?string $station = null,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            from: Carbon::parse($data['from'] ?? now()->subDays(29)->toDateString())->startOfDay(),
            to: Carbon::parse($data['to'] ?? now()->toDateString())->endOfDay(),
            section: self::stringOrNull($data['section'] ?? null),
            category: self::stringOrNull($data['category'] ?? null),
            service: self::stringOrNull($data['service'] ?? null),
            station: self::stringOrNull($data['station'] ?? null),
        );
    }

    /**
     * @return Builder<Ticket>
     */
    public function apply(Builder $query): Builder
    {
        return $query
            ->whereBetween('issued_at', [$this->from, $this->to])
            ->when(
                $this->section !== null,
                fn (Builder $query): Builder => $query->whereHas(
                    'section',
                    fn (Builder $query): Builder => $query->where('slug', $this->section),
                ),
            )
            ->when(
                $this->category !== null,
                fn (Builder $query): Builder => $query->whereHas(
                    'category',
                    fn (Builder $query): Builder => $query->where('slug', $this->category),
                ),
            )
            ->when(
                $this->service !== null,
                fn (Builder $query): Builder => $query->whereHas(
                    'service',
                    fn (Builder $query): Builder => $query->where('slug', $this->service),
                ),
            )
            ->when(
                $this->station !== null,
                fn (Builder $query): Builder => $query->where('station_label', $this->station),
            );
    }

    /**
     * @return array{from: string, to: string, section: ?string, category: ?string, service: ?string, station: ?string}
     */
    public function toArray(): array
    {
        return [
            'from' => $this->from->toDateString(),
            'to' => $this->to->toDateString(),
            'section' => $this->section,
            'category' => $this->category,
            'service' => $this->service,
            'station' => $this->station,
        ];
    }

    /**
     * @return array{from: string, to: string}
     */
    public function period(): array
    {
        return [
            'from' => $this->from->toDateString(),
            'to' => $this->to->toDateString(),
        ];
    }

    private static function stringOrNull(mixed $value): ?string
    {
        return is_string($value) && trim($value) !== '' ? trim($value) : null;
    }
}
