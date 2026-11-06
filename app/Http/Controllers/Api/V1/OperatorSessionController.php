<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Operations\Actions\ChangeOperatorStation;
use App\Domain\Operations\Actions\CloseOperatorSession;
use App\Domain\Operations\Actions\OpenOperatorSession;
use App\Domain\Operations\OperatorSession;
use App\Domain\Sections\Section;
use App\Domain\Users\UserRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\ChangeOperatorStationRequest;
use App\Http\Requests\Api\V1\OpenOperatorSessionRequest;
use App\Http\Resources\OperatorResource;
use App\Http\Resources\OperatorSessionResource;
use App\Http\Resources\StationResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class OperatorSessionController extends Controller
{
    public function operators(): JsonResponse
    {
        $operators = User::query()
            ->where('is_active', true)
            ->whereIn('role', [UserRole::Operator, UserRole::Supervisor])
            ->orderBy('name')
            ->get();

        return OperatorResource::collection($operators)->response();
    }

    public function stations(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'section' => [
                'required',
                'string',
                Rule::exists('sections', 'slug')->where('is_active', true),
            ],
        ]);

        $section = Section::query()->where('slug', $validated['section'])->firstOrFail();

        $stations = $section->stations()->where('is_active', true)->get();

        return StationResource::collection($stations)->response();
    }

    public function store(
        OpenOperatorSessionRequest $request,
        OpenOperatorSession $action,
    ): JsonResponse {
        $session = $action->handle(
            (int) $request->validated('operator_id'),
            $request->validated('section'),
            (int) $request->validated('station_id'),
        );

        return response()->json([
            'data' => new OperatorSessionResource($session),
            'message' => 'Sesión de operador abierta.',
        ], 201);
    }

    public function show(OperatorSession $session): JsonResponse
    {
        $session->load(['operator', 'section', 'activeAssignment.station', 'assignments.station']);

        return response()->json([
            'data' => new OperatorSessionResource($session),
        ]);
    }

    public function close(
        OperatorSession $session,
        CloseOperatorSession $action,
    ): JsonResponse {
        $session = $action->handle($session);

        return response()->json([
            'data' => new OperatorSessionResource($session),
            'message' => 'Sesión de operador cerrada.',
        ]);
    }

    public function changeStation(
        ChangeOperatorStationRequest $request,
        OperatorSession $session,
        ChangeOperatorStation $action,
    ): JsonResponse {
        $session = $action->handle($session, (int) $request->validated('station_id'));

        return response()->json([
            'data' => new OperatorSessionResource($session),
            'message' => 'Ventanilla actualizada.',
        ]);
    }
}
