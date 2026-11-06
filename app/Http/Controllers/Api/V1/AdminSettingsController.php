<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Reports\RetentionPolicy;
use App\Domain\Settings\DayClosingSchedule;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\UpdateAdminSettingsRequest;
use Illuminate\Http\JsonResponse;

class AdminSettingsController extends Controller
{
    public function show(
        DayClosingSchedule $schedule,
        RetentionPolicy $retention,
    ): JsonResponse {
        return response()->json([
            'data' => $this->payload($schedule, $retention),
        ]);
    }

    public function update(
        UpdateAdminSettingsRequest $request,
        DayClosingSchedule $schedule,
        RetentionPolicy $retention,
    ): JsonResponse {
        $schedule->setClosingTime($request->validated('closing_time'));

        if ($request->filled('retention_months')) {
            $retention->setMonths((int) $request->validated('retention_months'));
        }

        return response()->json([
            'data' => $this->payload($schedule, $retention),
            'message' => 'Configuración actualizada.',
        ]);
    }

    /**
     * @return array{closing_time: string, next_closing_at: string, timezone: string, retention_months: int}
     */
    private function payload(DayClosingSchedule $schedule, RetentionPolicy $retention): array
    {
        return [
            'closing_time' => $schedule->closingTime(),
            'next_closing_at' => $schedule->nextClosingAt()->toIso8601String(),
            'timezone' => (string) config('app.timezone'),
            'retention_months' => $retention->months(),
        ];
    }
}
