<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Operations\Actions\TransferTicket;
use App\Domain\Operations\OperatorSession;
use App\Domain\Sections\Section;
use App\Domain\Ticketing\Actions\CallNextTicket;
use App\Domain\Ticketing\Actions\FinishTicket;
use App\Domain\Ticketing\Actions\RecallTicket;
use App\Domain\Ticketing\Actions\StartTicket;
use App\Domain\Ticketing\Ticket;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\CallNextTicketRequest;
use App\Http\Requests\Api\V1\TransferTicketRequest;
use App\Http\Resources\OperatorSessionResource;
use App\Http\Resources\StationResource;
use App\Http\Resources\TicketResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ConsoleController extends Controller
{
    public function queue(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'section' => [
                'required',
                'string',
                Rule::exists('sections', 'slug')->where('is_active', true),
            ],
            'station' => ['nullable', 'string', 'max:50'],
        ]);

        $section = Section::query()->where('slug', $validated['section'])->firstOrFail();

        $session = OperatorSession::query()
            ->active()
            ->where('operator_id', $request->user()->id)
            ->with(['operator', 'section', 'activeAssignment.station', 'assignments.station'])
            ->first();

        $stationLabel = $validated['station']
            ?? $session?->activeAssignment?->station?->name;

        $today = now();
        $activeTicket = null;

        if ($stationLabel !== null && $stationLabel !== '') {
            $activeTicket = Ticket::query()
                ->active()
                ->forPeriod($today)
                ->where('current_section_id', $section->id)
                ->where('station_label', $stationLabel)
                ->with(['section', 'currentSection', 'category', 'service'])
                ->first();
        }

        return response()->json([
            'data' => [
                'section' => [
                    'slug' => $section->slug,
                    'name' => $section->name,
                ],
                'waiting_count' => Ticket::query()
                    ->waiting()
                    ->forPeriod($today)
                    ->where('current_section_id', $section->id)
                    ->count(),
                'active_ticket' => $activeTicket ? new TicketResource($activeTicket) : null,
                'session' => $session ? new OperatorSessionResource($session) : null,
                'stations' => StationResource::collection(
                    $section->stations()->where('is_active', true)->get(),
                )->resolve(),
            ],
        ]);
    }

    public function next(CallNextTicketRequest $request, CallNextTicket $action): JsonResponse
    {
        $this->requireActiveSession($request);

        $ticket = $action->handle(
            $request->validated('section'),
            $request->validated('station'),
            $request->user()->id,
        );

        return response()->json([
            'data' => $ticket ? new TicketResource($ticket) : null,
            'message' => $ticket
                ? 'Ticket llamado.'
                : 'No hay tickets en espera para esta sección.',
        ]);
    }

    public function recall(Ticket $ticket, RecallTicket $action): JsonResponse
    {
        $this->authorize('operate', $ticket);

        return response()->json([
            'data' => new TicketResource($action->handle($ticket)),
        ]);
    }

    public function start(Ticket $ticket, StartTicket $action): JsonResponse
    {
        $this->authorize('operate', $ticket);

        return response()->json([
            'data' => new TicketResource($action->handle($ticket)),
        ]);
    }

    public function finish(Ticket $ticket, FinishTicket $action): JsonResponse
    {
        $this->authorize('operate', $ticket);

        return response()->json([
            'data' => new TicketResource($action->handle($ticket)),
        ]);
    }

    public function transfer(
        TransferTicketRequest $request,
        Ticket $ticket,
        TransferTicket $action,
    ): JsonResponse {
        $this->authorize('transfer', $ticket);

        $ticket = $action->handle(
            $ticket,
            $request->validated('section'),
            $request->user()->id,
            $request->validated('to_operator_id'),
            $request->validated('reason'),
        );

        return response()->json([
            'data' => new TicketResource($ticket),
            'message' => 'Ticket transferido a la sección de destino.',
        ]);
    }

    private function requireActiveSession(Request $request): OperatorSession
    {
        $session = OperatorSession::query()
            ->active()
            ->where('operator_id', $request->user()->id)
            ->first();

        abort_unless(
            $session !== null,
            403,
            'Debe abrir una jornada antes de llamar tickets.',
        );

        return $session;
    }
}
