<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Settings\DayClosingSchedule;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\UpdateAdminSettingsRequest;
use Illuminate\Http\JsonResponse;

class AdminSettingsController extends Controller
{
    public function show(DayClosingSchedule $schedule): JsonResponse
    {
        return response()->json([
            'data' => $this->payload($schedule),
        ]);
    }

    public function update(
        UpdateAdminSettingsRequest $request,
        DayClosingSchedule $schedule,
    ): JsonResponse {
        $schedule->setClosingTime($request->validated('closing_time'));

        return response()->json([
            'data' => $this->payload($schedule),
            'message' => 'Hora de cierre actualizada.',
        ]);
    }

    /**
     * @return array{closing_time: string, next_closing_at: string, timezone: string}
     */
    private function payload(DayClosingSchedule $schedule): array
    {
        return [
            'closing_time' => $schedule->closingTime(),
            'next_closing_at' => $schedule->nextClosingAt()->toIso8601String(),
            'timezone' => (string) config('app.timezone'),
        ];
    }
}
