<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Audit\AuditLogger;
use App\Domain\Media\Playlist;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\PlaylistRequest;
use App\Http\Resources\PlaylistResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;

class AdminPlaylistController extends Controller
{
    public function index(): AnonymousResourceCollection
    {
        $playlists = Playlist::query()
            ->with(['items.mediaAsset'])
            ->orderByDesc('is_active')
            ->orderBy('name')
            ->get();

        return PlaylistResource::collection($playlists);
    }

    public function store(PlaylistRequest $request, AuditLogger $audit): JsonResponse
    {
        $playlist = DB::transaction(function () use ($request): Playlist {
            $playlist = Playlist::query()->create([
                'name' => $request->validated('name'),
                'description' => $request->validated('description'),
                'is_active' => $request->boolean('is_active', true),
            ]);

            $this->syncItems($playlist, $request->validated('items'));

            return $playlist;
        });

        $playlist->load('items.mediaAsset');

        $audit->record('playlist.created', $playlist, null, $this->payload($playlist), $request);

        return response()->json([
            'data' => new PlaylistResource($playlist),
            'message' => 'Playlist creada.',
        ], 201);
    }

    public function update(
        PlaylistRequest $request,
        Playlist $playlist,
        AuditLogger $audit,
    ): JsonResponse {
        $before = $this->payload($playlist);

        DB::transaction(function () use ($request, $playlist): void {
            $playlist->update([
                'name' => $request->validated('name'),
                'description' => $request->validated('description'),
                'is_active' => $request->has('is_active')
                    ? $request->boolean('is_active')
                    : $playlist->is_active,
            ]);

            $this->syncItems($playlist, $request->validated('items'));
        });

        $playlist->load('items.mediaAsset');

        $audit->record('playlist.updated', $playlist, $before, $this->payload($playlist), $request);

        return response()->json([
            'data' => new PlaylistResource($playlist),
            'message' => 'Playlist actualizada.',
        ]);
    }

    public function destroy(Playlist $playlist, AuditLogger $audit): JsonResponse
    {
        $before = $this->payload($playlist);
        $playlist->delete();
        $audit->record('playlist.deleted', $playlist, $before, null);

        return response()->json([
            'message' => 'Playlist eliminada.',
        ]);
    }

    /**
     * @param  array<int, array<string, mixed>>  $items
     */
    private function syncItems(Playlist $playlist, array $items): void
    {
        $playlist->items()->delete();

        foreach ($items as $position => $item) {
            $playlist->items()->create([
                'media_asset_id' => $item['media_asset_id'],
                'position' => $position,
                'duration_seconds' => $item['duration_seconds'] ?? null,
                'is_active' => (bool) ($item['is_active'] ?? true),
            ]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(Playlist $playlist): array
    {
        return [
            'name' => $playlist->name,
            'description' => $playlist->description,
            'is_active' => $playlist->is_active,
            'items' => $playlist->items
                ->map(fn ($item): array => [
                    'media_asset_id' => $item->media_asset_id,
                    'duration_seconds' => $item->duration_seconds,
                    'is_active' => $item->is_active,
                ])
                ->values()
                ->all(),
        ];
    }
}
