<?php

namespace App\Domain\Operations\Actions;

use App\Domain\Operations\OperatorSession;
use App\Domain\Ticketing\Ticket;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class CloseOperatorSession
{
    public function handle(OperatorSession $session, ?CarbonInterface $moment = null): OperatorSession
    {
        $now = $moment ? Carbon::instance($moment) : now();

        return DB::transaction(function () use ($session, $now): OperatorSession {
            $session = OperatorSession::query()->lockForUpdate()->findOrFail($session->id);

            if (! $session->isActive()) {
                return $session->load(['operator', 'section', 'activeAssignment.station', 'assignments.station']);
            }

            $hasActiveTicket = Ticket::query()
                ->active()
                ->where('operator_id', $session->operator_id)
                ->exists();

            if ($hasActiveTicket) {
                throw ValidationException::withMessages([
                    'session' => ['Finalice o libere el ticket activo antes de cerrar la sesión.'],
                ]);
            }

            $session->assignments()
                ->whereNull('released_at')
                ->update(['released_at' => $now]);

            $session->update(['ended_at' => $now]);

            return $session->load(['operator', 'section', 'activeAssignment.station', 'assignments.station']);
        });
    }
}
