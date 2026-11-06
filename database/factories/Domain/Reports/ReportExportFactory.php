<?php

namespace Database\Factories\Domain\Reports;

use App\Domain\Reports\ReportExport;
use App\Domain\Reports\ReportExportStatus;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ReportExport>
 */
class ReportExportFactory extends Factory
{
    protected $model = ReportExport::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'type' => 'tickets',
            'format' => 'csv',
            'status' => ReportExportStatus::Queued,
            'filters' => [
                'from' => now()->subDays(30)->toDateString(),
                'to' => now()->toDateString(),
                'section' => null,
                'category' => null,
                'service' => null,
                'station' => null,
            ],
            'rows' => 0,
            'file_path' => null,
            'file_size' => null,
            'error' => null,
            'started_at' => null,
            'completed_at' => null,
        ];
    }

    public function completed(): static
    {
        return $this->state(fn (): array => [
            'status' => ReportExportStatus::Completed,
            'rows' => 10,
            'file_path' => 'reports/demo.csv',
            'file_size' => 1024,
            'started_at' => now()->subMinute(),
            'completed_at' => now(),
        ]);
    }
}
