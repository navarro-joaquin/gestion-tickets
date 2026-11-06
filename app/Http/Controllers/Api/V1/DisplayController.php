<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Displays\Display;
use App\Domain\Sections\Section;
use App\Domain\Ticketing\Ticket;
use App\Http\Controllers\Controller;
use App\Http\Resources\PlaylistResource;
use App\Http\Resources\TicketResource;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DisplayController extends Controller
{
    public function snapshot(Request $request): JsonResponse
    {
        $display = null;
        $sections = Section::query()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('slug');

        if ($token = $request->query('display')) {
            $display = Display::query()
                ->where('token', $token)
                ->where('is_active', true)
                ->first();

            abort_if($display === null, 404, 'Pantalla no encontrada o inactiva.');

            $this->touchDisplay($display);

            $sections = $display->sections()
                ->where('sections.is_active', true);
        }

        $sections = $sections->get();

        $activeTickets = Ticket::query()
            ->active()
            ->with(['section', 'currentSection', 'category'])
            ->orderBy('called_at')
            ->get()
            ->groupBy('current_section_id');

        $waitingCounts = Ticket::query()
            ->waiting()
            ->selectRaw('current_section_id, count(*) as total')
            ->groupBy('current_section_id')
            ->pluck('total', 'current_section_id');

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
            'display' => $display === null ? null : [
                'id' => $display->id,
                'name' => $display->name,
                'slug' => $display->slug,
                'layout' => $display->layout,
            ],
            'playlists' => $display === null ? [] : $this->playlists($display),
            'updated_at' => now()->toIso8601String(),
        ]);
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function playlists(Display $display): array
    {
        $playlists = $display->playlists()
            ->where('playlists.is_active', true)
            ->with([
                'items' => fn ($query) => $query
                    ->where('is_active', true)
                    ->whereHas('mediaAsset', fn (Builder $query): Builder => $query->where('is_active', true))
                    ->with('mediaAsset'),
            ])
            ->get();

        return PlaylistResource::collection($playlists)->resolve();
    }

    private function touchDisplay(Display $display): void
    {
        if ($display->last_seen_at !== null && $display->last_seen_at->gt(now()->subMinute())) {
            return;
        }

        $display->forceFill(['last_seen_at' => now()])->save();
    }
}
