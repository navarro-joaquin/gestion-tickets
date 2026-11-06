<?php

namespace App\Domain\Reports;

use App\Domain\Settings\Setting;
use Carbon\CarbonInterface;
use Illuminate\Validation\ValidationException;

final class RetentionPolicy
{
    public const SETTING_KEY = 'tickets.retention_months';

    public const DEFAULT_MONTHS = 12;

    public function months(): int
    {
        $value = (int) Setting::query()
            ->where('key', self::SETTING_KEY)
            ->value('value');

        return $value >= 1 && $value <= 120 ? $value : self::DEFAULT_MONTHS;
    }

    public function setMonths(int $months): void
    {
        if ($months < 1 || $months > 120) {
            throw ValidationException::withMessages([
                'retention_months' => ['La retención debe estar entre 1 y 120 meses.'],
            ]);
        }

        Setting::query()->updateOrCreate(
            ['key' => self::SETTING_KEY],
            ['value' => (string) $months],
        );
    }

    public function cutoff(?CarbonInterface $moment = null): CarbonInterface
    {
        return ($moment ?? now())->copy()->subMonthsNoOverflow($this->months());
    }
}
