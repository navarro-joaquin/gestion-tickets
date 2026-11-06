<?php

namespace App\Domain\Settings;

use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Validation\ValidationException;

final class DayClosingSchedule
{
    public const SETTING_KEY = 'tickets.day_closing_time';

    public const DEFAULT_CLOSING_TIME = '16:30';

    public function closingTime(): string
    {
        $value = Setting::query()
            ->where('key', self::SETTING_KEY)
            ->value('value');

        return $this->isValidTime($value) ? $value : self::DEFAULT_CLOSING_TIME;
    }

    public function setClosingTime(string $time): void
    {
        if (! $this->isValidTime($time)) {
            throw ValidationException::withMessages([
                'closing_time' => ['La hora de cierre debe tener el formato HH:MM.'],
            ]);
        }

        Setting::query()->updateOrCreate(
            ['key' => self::SETTING_KEY],
            ['value' => $time],
        );
    }

    public function shouldCloseNow(?CarbonInterface $moment = null): bool
    {
        $now = $moment ? Carbon::instance($moment) : now();

        return $now->format('H:i') >= $this->closingTime();
    }

    public function nextClosingAt(?CarbonInterface $moment = null): CarbonInterface
    {
        $now = $moment ? Carbon::instance($moment) : now();
        $next = $now->copy()->setTimeFromTimeString($this->closingTime());

        return $next->greaterThan($now) ? $next : $next->addDay();
    }

    private function isValidTime(mixed $value): bool
    {
        return is_string($value) && preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $value) === 1;
    }
}
