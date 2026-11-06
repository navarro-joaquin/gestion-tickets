<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Audit\AuditLogger;
use App\Domain\Operations\Station;
use App\Domain\Operations\StationAssignment;
use App\Domain\Sections\Section;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\StationRequest;
use App\Http\Resources\StationResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\ValidationException;

class AdminStationController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $validated = $request->validate([
            'section' => ['nullable', 'string', 'max:80'],
        ]);

        $stations = Station::query()
            ->with('section')
            ->when(
                ! empty($validated['section']),
                fn ($query) => $query->whereHas(
                    'section',
                    fn ($query) => $query->where('slug', $validated['section']),
                ),
            )
            ->orderBy('section_id')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        return StationResource::collection($stations);
    }

    public function store(StationRequest $request, AuditLogger $audit): JsonResponse
    {
        $section = Section::query()
            ->where('slug', $request->validated('section'))
            ->firstOrFail();

        $station = Station::query()->create([
            'section_id' => $section->id,
            'name' => $request->validated('name'),
            'is_active' => $request->validated('is_active'),
            'sort_order' => $request->validated('sort_order') ?? 0,
        ]);

        $audit->record('station.created', $station, null, $this->payload($station), $request);

        $station->load('section');

        return response()->json([
            'data' => new StationResource($station),
            'message' => 'Ventanilla creada.',
        ], 201);
    }

    public function update(
        StationRequest $request,
        Station $station,
        AuditLogger $audit,
    ): JsonResponse {
        if (! $request->validated('is_active') && $this->hasActiveAssignment($station)) {
            throw ValidationException::withMessages([
                'is_active' => ['No se puede desactivar una ventanilla con una sesión de operador activa.'],
            ]);
        }

        $before = $this->payload($station);

        $station->update([
            'name' => $request->validated('name'),
            'is_active' => $request->validated('is_active'),
            'sort_order' => $request->validated('sort_order') ?? $station->sort_order,
        ]);

        $audit->record('station.updated', $station, $before, $this->payload($station), $request);

        $station->load('section');

        return response()->json([
            'data' => new StationResource($station),
            'message' => 'Ventanilla actualizada.',
        ]);
    }

    public function destroy(Station $station, AuditLogger $audit): JsonResponse
    {
        if (StationAssignment::query()->where('station_id', $station->id)->exists()) {
            $before = $this->payload($station);
            $station->update(['is_active' => false]);
            $audit->record('station.deactivated', $station, $before, $this->payload($station));

            return response()->json([
                'message' => 'La ventanilla tiene historial: se desactivó en lugar de eliminarse.',
            ]);
        }

        $before = $this->payload($station);
        $station->delete();
        $audit->record('station.deleted', $station, $before, null);

        return response()->json([
            'message' => 'Ventanilla eliminada.',
        ]);
    }

    private function hasActiveAssignment(Station $station): bool
    {
        return StationAssignment::query()
            ->where('station_id', $station->id)
            ->whereNull('released_at')
            ->exists();
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(Station $station): array
    {
        return [
            'name' => $station->name,
            'is_active' => $station->is_active,
            'sort_order' => $station->sort_order,
        ];
    }
}
