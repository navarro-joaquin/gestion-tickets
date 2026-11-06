<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Priorities\PriorityProfile;
use App\Domain\Ticketing\Ticket;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\PriorityProfileRequest;
use App\Http\Resources\PriorityProfileResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class PriorityProfileController extends Controller
{
    public function index(): AnonymousResourceCollection
    {
        $profiles = PriorityProfile::query()
            ->orderByDesc('is_active')
            ->orderBy('id')
            ->get();

        return PriorityProfileResource::collection($profiles);
    }

    public function store(PriorityProfileRequest $request): JsonResponse
    {
        $profile = PriorityProfile::query()->create($request->validated());

        return response()->json([
            'data' => new PriorityProfileResource($profile),
            'message' => 'Perfil de prioridad creado.',
        ], 201);
    }

    public function update(
        PriorityProfileRequest $request,
        PriorityProfile $priorityProfile,
    ): JsonResponse {
        $priorityProfile->update($request->validated());

        return response()->json([
            'data' => new PriorityProfileResource($priorityProfile),
            'message' => 'Perfil de prioridad actualizado.',
        ]);
    }

    public function destroy(PriorityProfile $priorityProfile): JsonResponse
    {
        if (Ticket::query()->where('priority_profile_id', $priorityProfile->id)->exists()) {
            $priorityProfile->update(['is_active' => false]);

            return response()->json([
                'message' => 'El perfil tiene historial: se desactivó en lugar de eliminarse.',
            ]);
        }

        $priorityProfile->delete();

        return response()->json([
            'message' => 'Perfil de prioridad eliminado.',
        ]);
    }
}
