<?php

namespace App\Domain\Settings;

use Carbon\CarbonInterface;
use Illuminate\Validation\ValidationException;

final class NoShowPolicy
{
    public const CALLS_SETTING_KEY = 'tickets.no_show_calls';

    public const MINUTES_SETTING_KEY = 'tickets.no_show_minutes';

    public const DEFAULT_CALLS = 2;

    public const DEFAULT_MINUTES = 1;

    public const MIN_CALLS = 1;

    public const MAX_CALLS = 10;

    public const MIN_MINUTES = 1;

    public const MAX_MINUTES = 60;

    /**
     * Llamados necesarios antes de poder cerrar un ticket como no presentado.
     */
    public function calls(): int
    {
        return $this->integerSetting(self::CALLS_SETTING_KEY, self::MIN_CALLS, self::MAX_CALLS, self::DEFAULT_CALLS);
    }

    /**
     * Minutos de espera tras el último llamado antes de cerrarlo como no presentado.
     */
    public function minutes(): int
    {
        return $this->integerSetting(self::MINUTES_SETTING_KEY, self::MIN_MINUTES, self::MAX_MINUTES, self::DEFAULT_MINUTES);
    }

    public function setCalls(int $calls): void
    {
        if ($calls < self::MIN_CALLS || $calls > self::MAX_CALLS) {
            throw ValidationException::withMessages([
                'no_show_calls' => ['Los llamados antes de cerrar deben estar entre 1 y 10.'],
            ]);
        }

        Setting::query()->updateOrCreate(
            ['key' => self::CALLS_SETTING_KEY],
            ['value' => (string) $calls],
        );
    }

    public function setMinutes(int $minutes): void
    {
        if ($minutes < self::MIN_MINUTES || $minutes > self::MAX_MINUTES) {
            throw ValidationException::withMessages([
                'no_show_minutes' => ['Los minutos tras el último llamado deben estar entre 1 y 60.'],
            ]);
        }

        Setting::query()->updateOrCreate(
            ['key' => self::MINUTES_SETTING_KEY],
            ['value' => (string) $minutes],
        );
    }

    /**
     * Momento límite: los tickets llamados por última vez antes de él ya pueden cerrarse.
     */
    public function calledBefore(CarbonInterface $moment): CarbonInterface
    {
        return $moment->copy()->subMinutes($this->minutes());
    }

    private function integerSetting(string $key, int $min, int $max, int $default): int
    {
        $value = (int) Setting::query()
            ->where('key', $key)
            ->value('value');

        return $value >= $min && $value <= $max ? $value : $default;
    }
}
