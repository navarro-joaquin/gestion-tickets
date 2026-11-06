<?php

namespace App\Domain\Operations\Actions;

use App\Domain\Operations\OperatorSession;
use App\Domain\Operations\Station;
use App\Domain\Operations\StationAssignment;
use App\Domain\Sections\Section;
use App\Domain\Sections\SectionActivationGate;
use App\Domain\Users\UserRole;
use App\Models\User;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class OpenOperatorSession
{
    public function __construct(private readonly SectionActivationGate $gate) {}

    public function handle(
        int $operatorId,
        string $sectionSlug,
        ?int $stationId = null,
        ?CarbonInterface $moment = null,
    ): OperatorSession {
        $now = $moment ? Carbon::instance($moment) : now();

        return DB::transaction(function () use ($operatorId, $sectionSlug, $stationId, $now): OperatorSession {
            $operator = User::query()
                ->whereKey($operatorId)
                ->lockForUpdate()
                ->first();

            if (! $operator || ! $operator->is_active) {
                throw ValidationException::withMessages([
                    'operator_id' => ['El operador indicado no existe o está inactivo.'],
                ]);
            }

            if (! $operator->hasRole(UserRole::Operator, UserRole::Supervisor)) {
                throw ValidationException::withMessages([
                    'operator_id' => ['Solo un operador o supervisor puede abrir una sesión.'],
                ]);
            }

            $section = Section::query()
                ->where('slug', $sectionSlug)
                ->lockForUpdate()
                ->first();

            if (! $section) {
                throw ValidationException::withMessages([
                    'section' => ['La sección indicada no existe.'],
                ]);
            }

            if (! $this->gate->isOperational($section)) {
                throw ValidationException::withMessages([
                    'section' => ['La sección no está operativa: la matriz debe estar confirmada y ser válida.'],
                ]);
            }

            $alreadyOpen = OperatorSession::query()
                ->active()
                ->where('operator_id', $operator->id)
                ->lockForUpdate()
                ->exists();

            if ($alreadyOpen) {
                throw ValidationException::withMessages([
                    'operator_id' => ['El operador ya tiene una sesión abierta.'],
                ]);
            }

            $station = null;

            if ($stationId !== null) {
                $station = Station::query()
                    ->whereKey($stationId)
                    ->where('section_id', $section->id)
                    ->where('is_active', true)
                    ->lockForUpdate()
                    ->first();

                if (! $station) {
                    throw ValidationException::withMessages([
                        'station_id' => ['La ventanilla indicada no existe o no pertenece a la sección.'],
                    ]);
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
            }

            $session = OperatorSession::query()->create([
                'operator_id' => $operator->id,
                'section_id' => $section->id,
                'started_at' => $now,
            ]);

            if ($station) {
                StationAssignment::query()->create([
                    'operator_session_id' => $session->id,
                    'station_id' => $station->id,
                    'assigned_at' => $now,
                ]);
            }

            return $session->load(['operator', 'section', 'activeAssignment.station']);
        });
    }
}
