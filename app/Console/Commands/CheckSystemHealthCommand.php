<?php

namespace App\Console\Commands;

use App\Domain\Settings\DayClosingSchedule;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Throwable;

#[Signature('tickets:health {--queue-threshold=100 : Máximo de jobs pendientes antes de advertir}')]
#[Description('Verifica la salud operativa del sistema de tickets para el piloto')]
class CheckSystemHealthCommand extends Command
{
    /**
     * @var list<array{check: string, status: string, detail: string}>
     */
    private array $results = [];

    public function handle(DayClosingSchedule $closing): int
    {
        $this->checkDatabase();
        $this->checkStorage('public', 'Multimedia y archivos públicos');
        $this->checkStorage('local', 'Archivos privados (reportes y respaldos)');
        $this->checkClosingTime($closing);
        $this->checkQueueBacklog((int) $this->option('queue-threshold'));

        $this->table(
            ['Comprobación', 'Estado', 'Detalle'],
            array_map(
                fn (array $result): array => [
                    $result['check'],
                    $result['status'],
                    $result['detail'],
                ],
                $this->results,
            ),
        );

        $failed = array_filter(
            $this->results,
            fn (array $result): bool => $result['status'] === 'FALL',
        );

        if ($failed !== []) {
            $this->components->error('Hay comprobaciones fallidas. Revisa el runbook de operación.');

            return self::FAILURE;
        }

        $warnings = array_filter(
            $this->results,
            fn (array $result): bool => $result['status'] === 'ADVERTENCIA',
        );

        $this->components->info(
            $warnings === []
                ? 'Sistema operativo: todas las comprobaciones pasaron.'
                : 'Sistema operativo con advertencias; revisa los detalles.',
        );

        return self::SUCCESS;
    }

    private function checkDatabase(): void
    {
        try {
            DB::connection()->getPdo();
            DB::select('select 1');

            $missing = array_values(array_filter(
                ['sections', 'tickets', 'ticket_sequences', 'settings', 'jobs'],
                fn (string $table): bool => ! Schema::hasTable($table),
            ));

            if ($missing !== []) {
                $this->record('Base de datos', 'FALL', 'Faltan tablas: '.implode(', ', $missing));

                return;
            }

            $this->record('Base de datos', 'OK', 'Conexión y tablas principales disponibles.');
        } catch (Throwable $exception) {
            $this->record('Base de datos', 'FALL', $exception->getMessage());
        }
    }

    private function checkStorage(string $disk, string $label): void
    {
        try {
            $path = '.health-check-'.now()->format('YmdHis');
            Storage::disk($disk)->put($path, 'ok');
            $readable = Storage::disk($disk)->exists($path);
            Storage::disk($disk)->delete($path);

            $this->record(
                $label,
                $readable ? 'OK' : 'FALL',
                $readable ? "Disco {$disk} de lectura y escritura." : "No se pudo leer el disco {$disk}.",
            );
        } catch (Throwable $exception) {
            $this->record($label, 'FALL', $exception->getMessage());
        }
    }

    private function checkClosingTime(DayClosingSchedule $closing): void
    {
        try {
            $this->record(
                'Hora de cierre',
                'OK',
                'Cierre programado a las '.$closing->closingTime().'.',
            );
        } catch (Throwable $exception) {
            $this->record('Hora de cierre', 'FALL', $exception->getMessage());
        }
    }

    private function checkQueueBacklog(int $threshold): void
    {
        if (config('queue.default') !== 'database') {
            $this->record('Cola de trabajos', 'OMITIDO', 'La cola no usa el driver database.');

            return;
        }

        try {
            $pending = DB::table('jobs')->count();

            if ($pending > $threshold) {
                $this->record(
                    'Cola de trabajos',
                    'ADVERTENCIA',
                    "Hay {$pending} trabajos pendientes; revisa el worker de cola.",
                );

                return;
            }

            $this->record('Cola de trabajos', 'OK', "{$pending} trabajos pendientes.");
        } catch (Throwable $exception) {
            $this->record('Cola de trabajos', 'ADVERTENCIA', $exception->getMessage());
        }
    }

    private function record(string $check, string $status, string $detail): void
    {
        $this->results[] = [
            'check' => $check,
            'status' => $status,
            'detail' => $detail,
        ];
    }
}
