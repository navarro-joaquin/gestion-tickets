<?php

namespace App\Domain\Reports;

use App\Domain\Ticketing\Ticket;
use Illuminate\Database\Eloquent\Builder;

final class TicketCsvWriter
{
    /**
     * @var list<string>
     */
    public const HEADERS = [
        'ID',
        'Ticket',
        'Sección',
        'Sección actual',
        'Código',
        'Número',
        'Categoría',
        'Servicio',
        'Preferencial',
        'Estado',
        'Ventanilla',
        'Operador',
        'Emitido',
        'Llamado',
        'Llamados',
        'Inicio de atención',
        'Fin de atención',
        'Cierre',
        'Espera (s)',
        'Atención (s)',
        'Total (s)',
    ];

    /**
     * @param  Builder<Ticket>  $query
     */
    public function write(Builder $query, string $absolutePath): int
    {
        $handle = fopen($absolutePath, 'wb');

        if ($handle === false) {
            throw new \RuntimeException('No se pudo crear el archivo CSV.');
        }

        fwrite($handle, "\xEF\xBB\xBF");

        fputcsv($handle, self::HEADERS, ',', '"', '\\');

        $rows = 0;

        $query->chunkById(500, function ($tickets) use ($handle, &$rows): void {
            foreach ($tickets as $ticket) {
                fputcsv($handle, $this->row($ticket), ',', '"', '\\');
                $rows++;
            }
        });

        fclose($handle);

        return $rows;
    }

    /**
     * @return list<string|int|null>
     */
    public function row(Ticket $ticket): array
    {
        $row = [
            $ticket->id,
            $ticket->label,
            $ticket->section?->name,
            $ticket->currentSection?->name,
            $ticket->code,
            $ticket->number,
            $ticket->category?->name,
            $ticket->service?->name ?? $ticket->service_label,
            $ticket->is_preferential ? 'Sí' : 'No',
            $ticket->status->label(),
            $ticket->station_label,
            $ticket->operator?->name,
            $ticket->issued_at?->format('Y-m-d H:i:s'),
            $ticket->called_at?->format('Y-m-d H:i:s'),
            $ticket->call_count,
            $ticket->started_at?->format('Y-m-d H:i:s'),
            $ticket->finished_at?->format('Y-m-d H:i:s'),
            $ticket->closed_at?->format('Y-m-d H:i:s'),
            $this->secondsBetween($ticket->issued_at, $ticket->called_at),
            $this->secondsBetween($ticket->started_at, $ticket->finished_at),
            $this->secondsBetween($ticket->issued_at, $ticket->closed_at),
        ];

        return array_map(
            fn (string|int|null $value): string|int|null => $this->neutralizeFormula($value),
            $row,
        );
    }

    /**
     * Evita la ejecución de fórmulas al abrir el CSV en una hoja de cálculo.
     */
    private function neutralizeFormula(string|int|null $value): string|int|null
    {
        if (! is_string($value) || $value === '') {
            return $value;
        }

        return preg_match('/^[=+\-@\t\r]/', $value) === 1
            ? "'{$value}"
            : $value;
    }

    private function secondsBetween(mixed $from, mixed $to): int|string
    {
        if ($from === null || $to === null) {
            return '';
        }

        return (int) $from->diffInSeconds($to, true);
    }
}
