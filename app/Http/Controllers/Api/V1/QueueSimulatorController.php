<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Priorities\PriorityProfile;
use App\Domain\Priorities\PriorityProfileResolver;
use App\Domain\Priorities\QueueSimulator;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\SimulateQueueRequest;
use App\Http\Resources\PriorityProfileResource;
use Illuminate\Http\JsonResponse;

class QueueSimulatorController extends Controller
{
    public function simulate(
        SimulateQueueRequest $request,
        QueueSimulator $simulator,
        PriorityProfileResolver $resolver,
    ): JsonResponse {
        $profile = $request->validated('profile_id')
            ? PriorityProfile::query()->findOrFail($request->validated('profile_id'))
            : $resolver->resolve(now());

        $sequence = $simulator->simulate(
            (int) $request->validated('general_count'),
            (int) $request->validated('preferential_count'),
            $profile,
        );

        return response()->json([
            'data' => [
                'profile' => new PriorityProfileResource($profile),
                'sequence' => $sequence,
                'totals' => [
                    'general' => count(array_filter($sequence, fn (array $step): bool => $step['group'] === 'general')),
                    'preferential' => count(array_filter($sequence, fn (array $step): bool => $step['group'] === 'preferential')),
                ],
            ],
        ]);
    }
}
