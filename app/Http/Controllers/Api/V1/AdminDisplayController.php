<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Audit\AuditLogger;
use App\Domain\Displays\Display;
use App\Domain\Sections\Section;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\DisplayRequest;
use App\Http\Resources\DisplayResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Str;

class AdminDisplayController extends Controller
{
    public function index(): AnonymousResourceCollection
    {
        $displays = Display::query()
            ->with(['sections', 'playlists.items'])
            ->orderBy('name')
            ->get();

        return DisplayResource::collection($displays);
    }

    public function store(DisplayRequest $request, AuditLogger $audit): JsonResponse
    {
        $display = Display::query()->create([
            'name' => $request->validated('name'),
            'slug' => $this->uniqueSlug($request->validated('slug') ?? $request->validated('name')),
            'token' => Str::random(48),
            'layout' => $request->validated('layout'),
            'is_active' => $request->validated('is_active'),
        ]);

        $this->syncRelations($display, $request);

        $display->load(['sections', 'playlists.items']);

        $audit->record('display.created', $display, null, $this->payload($display), $request);

        return response()->json([
            'data' => new DisplayResource($display),
            'message' => 'Pantalla creada.',
        ], 201);
    }

    public function update(
        DisplayRequest $request,
        Display $display,
        AuditLogger $audit,
    ): JsonResponse {
        $before = $this->payload($display);

        $display->update([
            'name' => $request->validated('name'),
            'slug' => $request->validated('slug') !== null
                ? $this->uniqueSlug($request->validated('slug'), $display->id)
                : $display->slug,
            'layout' => $request->validated('layout'),
            'is_active' => $request->validated('is_active'),
        ]);

        $this->syncRelations($display, $request);

        $display->load(['sections', 'playlists.items']);

        $audit->record('display.updated', $display, $before, $this->payload($display), $request);

        return response()->json([
            'data' => new DisplayResource($display),
            'message' => 'Pantalla actualizada.',
        ]);
    }

    public function destroy(Display $display, AuditLogger $audit): JsonResponse
    {
        $before = $this->payload($display);
        $display->delete();
        $audit->record('display.deleted', $display, $before, null);

        return response()->json([
            'message' => 'Pantalla eliminada.',
        ]);
    }

    public function rotateToken(Display $display, AuditLogger $audit): JsonResponse
    {
        $display->update(['token' => Str::random(48)]);
        $display->load(['sections', 'playlists.items']);

        $audit->record(
            'display.token.rotated',
            $display,
            null,
            ['token' => $display->token],
        );

        return response()->json([
            'data' => new DisplayResource($display),
            'message' => 'Token de pantalla regenerado.',
        ]);
    }

    private function syncRelations(Display $display, DisplayRequest $request): void
    {
        $display->sections()->sync($this->sectionIds($request->validated('sections')));
        $display->playlists()->sync($this->playlistPositions($request->validated('playlists') ?? []));
    }

    /**
     * @param  list<string>  $slugs
     * @return list<int>
     */
    private function sectionIds(array $slugs): array
    {
        return Section::query()
            ->whereIn('slug', $slugs)
            ->pluck('id')
            ->all();
    }

    /**
     * @param  list<int>  $playlistIds
     * @return array<int, array{position: int}>
     */
    private function playlistPositions(array $playlistIds): array
    {
        $positions = [];

        foreach (array_values($playlistIds) as $position => $playlistId) {
            $positions[(int) $playlistId] = ['position' => $position];
        }

        return $positions;
    }

    private function uniqueSlug(string $value, ?int $ignoreId = null): string
    {
        $base = Str::slug($value) ?: 'pantalla';
        $slug = $base;
        $suffix = 2;

        while (Display::query()
            ->where('slug', $slug)
            ->when($ignoreId !== null, fn ($query) => $query->whereKeyNot($ignoreId))
            ->exists()) {
            $slug = "{$base}-{$suffix}";
            $suffix++;
        }

        return $slug;
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(Display $display): array
    {
        return [
            'name' => $display->name,
            'slug' => $display->slug,
            'layout' => $display->layout,
            'is_active' => $display->is_active,
            'sections' => $display->sections->pluck('slug')->values()->all(),
            'playlists' => $display->playlists->pluck('id')->values()->all(),
        ];
    }
}
