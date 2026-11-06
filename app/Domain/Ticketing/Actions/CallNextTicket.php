<?php

namespace App\Domain\Ticketing\Actions;

use App\Domain\Operations\OperatorSession;
use App\Domain\Sections\Section;
use App\Domain\Sections\SectionActivationGate;
use App\Domain\Ticketing\AssignmentReason;
use App\Domain\Ticketing\QueueNotifier;
use App\Domain\Ticketing\Ticket;
use App\Domain\Ticketing\TicketAssignment;
use App\Domain\Ticketing\TicketEvent;
use App\Domain\Ticketing\TicketEventType;
use App\Domain\Ticketing\TicketStatus;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class CallNextTicket
{
    public function __construct(
        private readonly SectionActivationGate $gate,
        private readonly QueueNotifier $notifier,
    ) {}

    public function handle(
        string $sectionSlug,
        string $stationLabel,
        ?int $operatorId = null,
        ?CarbonInterface $moment = null,
    ): ?Ticket {
        $now = $moment ? Carbon::instance($moment) : now();

        $ticket = DB::transaction(function () use ($sectionSlug, $stationLabel, $operatorId, $now): ?Ticket {
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

            if (Ticket::query()
                ->active()
                ->where('current_section_id', $section->id)
                ->where('station_label', $stationLabel)
                ->exists()
            ) {
                throw ValidationException::withMessages([
                    'station' => ['La ventanilla ya tiene un ticket activo.'],
                ]);
            }

            if ($operatorId !== null && Ticket::query()->active()->where('operator_id', $operatorId)->exists()) {
                throw ValidationException::withMessages([
                    'operator_id' => ['El operador ya tiene un ticket activo.'],
                ]);
            }

            if ($operatorId !== null) {
                $session = OperatorSession::query()
                    ->active()
                    ->where('operator_id', $operatorId)
                    ->lockForUpdate()
                    ->first();

                if ($session) {
                    if ($session->section_id !== $section->id) {
                        throw ValidationException::withMessages([
                            'section' => ['El operador tiene una sesión abierta en otra sección.'],
                        ]);
                    }

                    $assignedStation = $session->activeAssignment()
                        ->with('station')
                        ->first();

                    if ($assignedStation && $assignedStation->station->name !== $stationLabel) {
                        throw ValidationException::withMessages([
                            'station' => ['La ventanilla no coincide con la asignada al operador.'],
                        ]);
                    }
                }
            }

            $ticket = Ticket::query()
                ->where('current_section_id', $section->id)
                ->where('status', TicketStatus::Waiting)
                ->orderBy('issued_at')
                ->orderBy('id')
                ->lockForUpdate()
                ->first();

            if (! $ticket) {
                return null;
            }

            $ticket->update([
                'status' => TicketStatus::Called,
                'station_label' => $stationLabel,
                'operator_id' => $operatorId,
                'called_at' => $now,
                'call_count' => $ticket->call_count + 1,
            ]);

            TicketAssignment::query()->create([
                'ticket_id' => $ticket->id,
                'operator_id' => $operatorId,
                'station_label' => $stationLabel,
                'reason' => AssignmentReason::Called,
                'assigned_at' => $now,
            ]);

            TicketEvent::query()->create([
                'ticket_id' => $ticket->id,
                'type' => TicketEventType::Called,
                'actor_id' => $operatorId,
                'occurred_at' => $now,
                'payload' => [
                    'station' => $stationLabel,
                    'call_count' => $ticket->call_count,
                ],
            ]);

            return $ticket;
        });

        if ($ticket) {
            $this->notifier->notify($sectionSlug);
        }

        return $ticket?->load(['section', 'currentSection', 'category', 'operator']);
    }
}
