<?php

namespace App\Domain\Operations\Actions;

use App\Domain\Sections\Section;
use App\Domain\Sections\SectionActivationGate;
use App\Domain\Ticketing\QueueNotifier;
use App\Domain\Ticketing\Ticket;
use App\Domain\Ticketing\TicketEvent;
use App\Domain\Ticketing\TicketEventType;
use App\Domain\Ticketing\TicketStatus;
use App\Domain\Ticketing\TicketTransfer;
use App\Models\User;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class TransferTicket
{
    public function __construct(
        private readonly SectionActivationGate $gate,
        private readonly QueueNotifier $notifier,
    ) {}

    public function handle(
        Ticket $ticket,
        string $toSectionSlug,
        ?int $fromOperatorId = null,
        ?int $toOperatorId = null,
        ?string $reason = null,
        ?CarbonInterface $moment = null,
    ): Ticket {
        $now = $moment ? Carbon::instance($moment) : now();

        [$ticket, $fromSlug] = DB::transaction(function () use (
            $ticket,
            $toSectionSlug,
            $fromOperatorId,
            $toOperatorId,
            $reason,
            $now,
        ): array {
            $ticket = Ticket::query()->lockForUpdate()->findOrFail($ticket->id);

            if (! in_array($ticket->status, [TicketStatus::Waiting, TicketStatus::Called], true)) {
                throw ValidationException::withMessages([
                    'ticket' => ['Solo se puede transferir un ticket en espera o llamado.'],
                ]);
            }

            $toSection = Section::query()
                ->where('slug', $toSectionSlug)
                ->lockForUpdate()
                ->first();

            if (! $toSection) {
                throw ValidationException::withMessages([
                    'section' => ['La sección de destino no existe.'],
                ]);
            }

            if (! $this->gate->isOperational($toSection)) {
                throw ValidationException::withMessages([
                    'section' => ['La sección de destino no está operativa.'],
                ]);
            }

            $fromSectionId = $ticket->current_section_id ?? $ticket->section_id;

            if ($fromSectionId === $toSection->id) {
                throw ValidationException::withMessages([
                    'section' => ['El ticket ya pertenece a la sección de destino.'],
                ]);
            }

            if ($toOperatorId !== null) {
                $exists = User::query()
                    ->whereKey($toOperatorId)
                    ->where('is_active', true)
                    ->exists();

                if (! $exists) {
                    throw ValidationException::withMessages([
                        'to_operator_id' => ['El operador de destino no existe o está inactivo.'],
                    ]);
                }
            }

            $poolData = [];

            if ($ticket->status === TicketStatus::Called) {
                $ticket->assignments()
                    ->whereNull('released_at')
                    ->update(['released_at' => $now]);

                $poolData = [
                    'status' => TicketStatus::Waiting,
                    'station_label' => null,
                    'operator_id' => null,
                    'called_at' => null,
                    'call_count' => 0,
                ];
            }

            $ticket->update([
                ...$poolData,
                'current_section_id' => $toSection->id,
            ]);

            TicketTransfer::query()->create([
                'ticket_id' => $ticket->id,
                'from_section_id' => $fromSectionId,
                'to_section_id' => $toSection->id,
                'from_operator_id' => $fromOperatorId,
                'to_operator_id' => $toOperatorId,
                'reason' => $reason,
                'transferred_at' => $now,
            ]);

            TicketEvent::query()->create([
                'ticket_id' => $ticket->id,
                'type' => TicketEventType::Transferred,
                'actor_id' => $fromOperatorId,
                'occurred_at' => $now,
                'payload' => [
                    'from_section_id' => $fromSectionId,
                    'to_section_id' => $toSection->id,
                    'to_section' => $toSection->slug,
                    'reason' => $reason,
                ],
            ]);

            $fromSlug = Section::query()->whereKey($fromSectionId)->value('slug');

            return [$ticket, $fromSlug];
        });

        $this->notifier->notify($toSectionSlug);

        if (is_string($fromSlug) && $fromSlug !== $toSectionSlug) {
            $this->notifier->notify($fromSlug);
        }

        return $ticket->load([
            'section',
            'currentSection',
            'category',
            'transfers.fromSection',
            'transfers.toSection',
            'transfers.fromOperator',
            'transfers.toOperator',
        ]);
    }
}
