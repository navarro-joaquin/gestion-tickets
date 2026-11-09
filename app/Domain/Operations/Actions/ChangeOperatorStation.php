<?php

namespace App\Domain\Operations\Actions;

use App\Domain\Operations\OperatorSession;
use App\Domain\Operations\Station;
use App\Domain\Operations\StationAssignment;
use App\Domain\Ticketing\Ticket;
use App\Models\User;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class ChangeOperatorStation
{
    public function handle(
        OperatorSession $session,
        int $stationId,
        ?CarbonInterface $moment = null,
    ): OperatorSession {
        $now = $moment ? Carbon::instance($moment) : now();

        return DB::transaction(function () use ($session, $stationId, $now): OperatorSession {
            $session = OperatorSession::query()->lockForUpdate()->findOrFail($session->id);

            if (! $session->isActive()) {
                throw ValidationException::withMessages([
                    'session' => ['La sesión ya está cerrada.'],
                ]);
            }

            $station = Station::query()
                ->whereKey($stationId)
                ->where('section_id', $session->section_id)
                ->where('is_active', true)
                ->lockForUpdate()
                ->first();

            if (! $station) {
                throw ValidationException::withMessages([
                    'station_id' => ['La ventanilla indicada no existe o no pertenece a la sección.'],
                ]);
            }

            $hasActiveTicket = Ticket::query()
                ->active()
                ->where('operator_id', $session->operator_id)
                ->exists();

            if ($hasActiveTicket) {
                throw ValidationException::withMessages([
                    'session' => ['Finalice o libere el ticket activo antes de cambiar de ventanilla.'],
                ]);
            }

            $currentAssignment = $session->activeAssignment()
                ->with('station')
                ->lockForUpdate()
                ->first();

            if ($currentAssignment?->station_id === $station->id) {
                return $session->load(['operator', 'section', 'activeAssignment.station']);
            }

            $stationOccupied = StationAssignment::query()
                ->where('station_id', $station->id)
                ->whereNull('released_at')
                ->lockForUpdate()
                ->exists();

            if ($stationOccupied) {
                throw ValidationException::withMessages([
                    'station_id' => ['La ventanilla ya está asignada a otro operador.'],
                ]);
            }

            $currentAssignment?->update(['released_at' => $now]);

            StationAssignment::query()->create([
                'operator_session_id' => $session->id,
                'station_id' => $station->id,
                'assigned_at' => $now,
            ]);

            User::query()
                ->whereKey($session->operator_id)
                ->update([
                    'default_section_id' => $session->section_id,
                    'default_station_id' => $station->id,
                ]);

            return $session->load(['operator', 'section', 'activeAssignment.station']);
        });
    }
}
