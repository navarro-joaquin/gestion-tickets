<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Sections\Section;
use App\Domain\Ticketing\Ticket;
use App\Http\Controllers\Controller;
use App\Http\Resources\TicketResource;
use Illuminate\Http\JsonResponse;

class DisplayController extends Controller
{
    public function snapshot(): JsonResponse
    {
        $sections = Section::query()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('slug')
            ->get();

        $activeTickets = Ticket::query()
            ->active()
            ->with(['section', 'category'])
            ->orderBy('called_at')
            ->get()
            ->groupBy('section_id');

        $waitingCounts = Ticket::query()
            ->waiting()
            ->selectRaw('section_id, count(*) as total')
            ->groupBy('section_id')
            ->pluck('total', 'section_id');

        return response()->json([
            'data' => $sections->map(fn (Section $section): array => [
                'section' => [
                    'slug' => $section->slug,
                    'name' => $section->name,
                ],
                'waiting_count' => (int) ($waitingCounts[$section->id] ?? 0),
                'calling' => ($activeTickets[$section->id] ?? collect())
                    ->map(fn (Ticket $ticket): TicketResource => new TicketResource($ticket))
                    ->values(),
            ])->values(),
            'updated_at' => now()->toIso8601String(),
        ]);
    }
}
