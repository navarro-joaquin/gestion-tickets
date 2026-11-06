<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Sections\Section;
use App\Domain\Ticketing\Actions\CallNextTicket;
use App\Domain\Ticketing\Actions\FinishTicket;
use App\Domain\Ticketing\Actions\RecallTicket;
use App\Domain\Ticketing\Actions\StartTicket;
use App\Domain\Ticketing\Ticket;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\CallNextTicketRequest;
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

        $activeTicket = null;

        if (! empty($validated['station'])) {
            $activeTicket = Ticket::query()
                ->active()
                ->where('section_id', $section->id)
                ->where('station_label', $validated['station'])
                ->with(['section', 'category'])
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
                    ->where('section_id', $section->id)
                    ->count(),
                'active_ticket' => $activeTicket ? new TicketResource($activeTicket) : null,
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
}
