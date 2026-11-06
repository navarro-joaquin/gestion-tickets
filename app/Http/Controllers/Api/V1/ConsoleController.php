<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Operations\Actions\RegisterPrintFailure;
use App\Domain\Operations\Actions\ReprintTicket;
use App\Domain\Operations\Actions\TransferTicket;
use App\Domain\Operations\OperatorSession;
use App\Domain\Sections\Section;
use App\Domain\Ticketing\Actions\CallNextTicket;
use App\Domain\Ticketing\Actions\FinishTicket;
use App\Domain\Ticketing\Actions\RecallTicket;
use App\Domain\Ticketing\Actions\StartTicket;
use App\Domain\Ticketing\Printing\EscPosTicketFormatter;
use App\Domain\Ticketing\Ticket;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\CallNextTicketRequest;
use App\Http\Requests\Api\V1\RegisterPrintFailureRequest;
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
            'operator_id' => [
                'nullable',
                'integer',
                Rule::exists('users', 'id'),
            ],
        ]);

        $section = Section::query()->where('slug', $validated['section'])->firstOrFail();
        $stationLabel = $validated['station'] ?? null;

        $activeTicket = null;

        if ($stationLabel !== null && $stationLabel !== '') {
            $activeTicket = Ticket::query()
                ->active()
                ->where('current_section_id', $section->id)
                ->where('station_label', $stationLabel)
                ->with(['section', 'currentSection', 'category'])
                ->first();
        }

        $session = null;

        if (! empty($validated['operator_id'])) {
            $session = OperatorSession::query()
                ->active()
                ->where('operator_id', $validated['operator_id'])
                ->with(['operator', 'section', 'activeAssignment.station', 'assignments.station'])
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
        $ticket = $action->handle(
            $request->validated('section'),
            $request->validated('station'),
            $request->validated('operator_id'),
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
        return response()->json([
            'data' => new TicketResource($action->handle($ticket)),
        ]);
    }

    public function start(Ticket $ticket, StartTicket $action): JsonResponse
    {
        return response()->json([
            'data' => new TicketResource($action->handle($ticket)),
        ]);
    }

    public function finish(Ticket $ticket, FinishTicket $action): JsonResponse
    {
        return response()->json([
            'data' => new TicketResource($action->handle($ticket)),
        ]);
    }

    public function transfer(
        TransferTicketRequest $request,
        Ticket $ticket,
        TransferTicket $action,
    ): JsonResponse {
        $ticket = $action->handle(
            $ticket,
            $request->validated('section'),
            $request->validated('from_operator_id'),
            $request->validated('to_operator_id'),
            $request->validated('reason'),
        );

        return response()->json([
            'data' => new TicketResource($ticket),
            'message' => 'Ticket transferido a la sección de destino.',
        ]);
    }

    public function reprint(
        Ticket $ticket,
        ReprintTicket $action,
        EscPosTicketFormatter $formatter,
    ): JsonResponse {
        $ticket = $action->handle($ticket);
        $ticket->loadMissing(['section', 'category']);

        return response()->json([
            'data' => new TicketResource($ticket),
            'print' => [
                'escpos_base64' => base64_encode($formatter->format($ticket)),
                'text' => $formatter->preview($ticket),
            ],
            'message' => 'Ticket reimpreso.',
        ]);
    }

    public function printFailed(
        RegisterPrintFailureRequest $request,
        Ticket $ticket,
        RegisterPrintFailure $action,
    ): JsonResponse {
        $ticket = $action->handle(
            $ticket,
            $request->validated('message'),
            $request->validated('operator_id'),
        );

        return response()->json([
            'data' => new TicketResource($ticket),
            'message' => 'Fallo de impresión registrado.',
        ]);
    }
}
