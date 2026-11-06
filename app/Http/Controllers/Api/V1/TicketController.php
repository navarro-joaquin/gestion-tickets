<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Ticketing\Actions\IssueTicket;
use App\Domain\Ticketing\Printing\EscPosTicketFormatter;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\IssueTicketRequest;
use App\Http\Resources\TicketResource;
use Illuminate\Http\JsonResponse;

class TicketController extends Controller
{
    public function store(
        IssueTicketRequest $request,
        IssueTicket $issueTicket,
        EscPosTicketFormatter $formatter,
    ): JsonResponse {
        $ticket = $issueTicket->handle(
            $request->validated('section'),
            $request->validated('category'),
        );

        return response()->json([
            'data' => new TicketResource($ticket),
            'print' => [
                'escpos_base64' => base64_encode($formatter->format($ticket)),
                'text' => $formatter->preview($ticket),
            ],
        ], 201);
    }
}
