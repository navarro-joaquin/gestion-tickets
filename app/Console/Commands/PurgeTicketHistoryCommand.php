<?php

namespace App\Console\Commands;

use App\Domain\Reports\RetentionPolicy;
use App\Domain\Reports\TicketCsvWriter;
use App\Domain\Reports\TicketReport;
use App\Domain\Ticketing\Ticket;
use App\Domain\Ticketing\TicketStatus;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Storage;

#[Signature('tickets:purge {--months= : Meses de retención; por defecto usa la configuración} {--dry-run : Solo cuenta los tickets que se eliminarían}')]
#[Description('Archiva en CSV y elimina los tickets cerrados que superan la retención configurada')]
class PurgeTicketHistoryCommand extends Command
{
    public function handle(
        RetentionPolicy $policy,
        TicketCsvWriter $writer,
        TicketReport $report,
    ): int {
        $months = $this->resolveMonths($policy);
        $cutoff = now()->subMonthsNoOverflow($months);
        $query = $this->purgeableQuery($cutoff);
        $total = (clone $query)->count();

        if ($total === 0) {
            $this->components->info("No hay tickets cerrados antes de {$cutoff->toDateTimeString()}.");

            return self::SUCCESS;
        }

        if ($this->option('dry-run')) {
            $this->components->info(
                "Se eliminarían {$total} tickets cerrados antes de {$cutoff->toDateTimeString()} (retención de {$months} meses).",
            );

            return self::SUCCESS;
        }

        $disk = Storage::disk('local');
        $disk->makeDirectory('archives');

        $relativePath = 'archives/tickets-'.$cutoff->format('Ymd').'-'.now()->format('YmdHis').'.csv';
        $absolutePath = $disk->path($relativePath);

        $archived = $writer->write(
            $query->with($report->relations()),
            $absolutePath,
        );

        $deleted = $this->purgeableQuery($cutoff)->delete();

        $this->components->info(
            "Archivados {$archived} tickets en {$relativePath} y eliminados {$deleted} registros.",
        );

        return self::SUCCESS;
    }

    private function resolveMonths(RetentionPolicy $policy): int
    {
        $option = $this->option('months');

        if ($option === null || $option === '') {
            return $policy->months();
        }

        return max(1, min(120, (int) $option));
    }

    /**
     * @return Builder<Ticket>
     */
    private function purgeableQuery(mixed $cutoff): Builder
    {
        return Ticket::query()
            ->whereIn('status', $this->terminalStatuses())
            ->whereNotNull('closed_at')
            ->where('closed_at', '<', $cutoff);
    }

    /**
     * @return list<string>
     */
    private function terminalStatuses(): array
    {
        return array_values(array_map(
            fn (TicketStatus $status): string => $status->value,
            array_filter(TicketStatus::cases(), fn (TicketStatus $status): bool => $status->isTerminal()),
        ));
    }
}
