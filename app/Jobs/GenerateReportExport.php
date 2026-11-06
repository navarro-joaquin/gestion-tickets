<?php

namespace App\Jobs;

use App\Domain\Reports\ReportExport;
use App\Domain\Reports\ReportExportStatus;
use App\Domain\Reports\ReportFilters;
use App\Domain\Reports\TicketCsvWriter;
use App\Domain\Reports\TicketReport;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Storage;
use Throwable;

class GenerateReportExport implements ShouldQueue
{
    use Queueable;

    public int $timeout = 900;

    public function __construct(public ReportExport $export) {}

    public function handle(TicketReport $report, TicketCsvWriter $writer): void
    {
        $this->export->refresh();

        if ($this->export->status === ReportExportStatus::Completed) {
            return;
        }

        $this->export->update([
            'status' => ReportExportStatus::Processing,
            'started_at' => now(),
            'error' => null,
        ]);

        $disk = Storage::disk('local');
        $disk->makeDirectory('reports');

        $relativePath = 'reports/tickets-'.$this->export->id.'-'.now()->format('YmdHis').'.csv';
        $absolutePath = $disk->path($relativePath);

        try {
            $query = $report->query(ReportFilters::fromArray($this->export->filters));
            $rows = $writer->write($query, $absolutePath);

            $this->export->update([
                'status' => ReportExportStatus::Completed,
                'rows' => $rows,
                'file_path' => $relativePath,
                'file_size' => $disk->size($relativePath),
                'completed_at' => now(),
            ]);
        } catch (Throwable $exception) {
            $disk->delete($relativePath);

            $this->export->update([
                'status' => ReportExportStatus::Failed,
                'error' => mb_substr($exception->getMessage(), 0, 900),
                'completed_at' => now(),
            ]);

            throw $exception;
        }
    }
}
