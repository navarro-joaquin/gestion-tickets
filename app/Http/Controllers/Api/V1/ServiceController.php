<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Sections\Section;
use App\Domain\Services\Service;
use App\Domain\Ticketing\Ticket;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\ServiceRequest;
use App\Http\Resources\ServiceResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class ServiceController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $validated = $request->validate([
            'section' => ['nullable', 'string', 'max:80'],
        ]);

        $services = Service::query()
            ->with(['section', 'operators'])
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

        return ServiceResource::collection($services);
    }

    public function store(ServiceRequest $request): JsonResponse
    {
        $service = Service::query()->create($this->payload($request));

        if ($request->has('operator_ids')) {
            $service->operators()->sync($request->validated('operator_ids') ?? []);
        }

        $service->load(['section', 'operators']);

        return response()->json([
            'data' => new ServiceResource($service),
            'message' => 'Servicio creado.',
        ], 201);
    }

    public function update(ServiceRequest $request, Service $service): JsonResponse
    {
        $service->update($this->payload($request));

        if ($request->has('operator_ids')) {
            $service->operators()->sync($request->validated('operator_ids') ?? []);
        }

        $service->load(['section', 'operators']);

        return response()->json([
            'data' => new ServiceResource($service),
            'message' => 'Servicio actualizado.',
        ]);
    }

    public function destroy(Service $service): JsonResponse
    {
        if (Ticket::query()->where('service_id', $service->id)->exists()) {
            $service->update(['is_active' => false]);

            return response()->json([
                'message' => 'El servicio tiene historial: se desactivó en lugar de eliminarse.',
            ]);
        }

        $service->delete();

        return response()->json([
            'message' => 'Servicio eliminado.',
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(ServiceRequest $request): array
    {
        $section = Section::query()
            ->where('slug', $request->validated('section'))
            ->firstOrFail();

        $data = $request->validated();
        unset($data['section'], $data['operator_ids']);

        $data['section_id'] = $section->id;
        $data['sort_order'] ??= 0;

        return $data;
    }
}
