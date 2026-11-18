<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Reports\RetentionPolicy;
use App\Domain\Settings\DayClosingSchedule;
use App\Domain\Settings\NoShowPolicy;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\UpdateAdminSettingsRequest;
use Illuminate\Http\JsonResponse;

class AdminSettingsController extends Controller
{
    public function show(
        DayClosingSchedule $schedule,
        RetentionPolicy $retention,
        NoShowPolicy $noShow,
    ): JsonResponse {
        return response()->json([
            'data' => $this->payload($schedule, $retention, $noShow),
        ]);
    }

    public function update(
        UpdateAdminSettingsRequest $request,
        DayClosingSchedule $schedule,
        RetentionPolicy $retention,
        NoShowPolicy $noShow,
    ): JsonResponse {
        $schedule->setClosingTime($request->validated('closing_time'));

        if ($request->filled('retention_months')) {
            $retention->setMonths((int) $request->validated('retention_months'));
        }

        if ($request->filled('no_show_calls')) {
            $noShow->setCalls((int) $request->validated('no_show_calls'));
        }

        if ($request->filled('no_show_minutes')) {
            $noShow->setMinutes((int) $request->validated('no_show_minutes'));
        }

        return response()->json([
            'data' => $this->payload($schedule, $retention, $noShow),
            'message' => 'Configuración actualizada.',
        ]);
    }

    /**
     * @return array{closing_time: string, next_closing_at: string, timezone: string, retention_months: int, no_show_calls: int, no_show_minutes: int}
     */
    private function payload(DayClosingSchedule $schedule, RetentionPolicy $retention, NoShowPolicy $noShow): array
    {
        return [
            'closing_time' => $schedule->closingTime(),
            'next_closing_at' => $schedule->nextClosingAt()->toIso8601String(),
            'timezone' => (string) config('app.timezone'),
            'retention_months' => $retention->months(),
            'no_show_calls' => $noShow->calls(),
            'no_show_minutes' => $noShow->minutes(),
        ];
    }
}
