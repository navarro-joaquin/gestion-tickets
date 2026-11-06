<?php

namespace Tests\Feature\Settings;

use App\Domain\Settings\DayClosingSchedule;
use App\Domain\Settings\Setting;
use Carbon\Carbon;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class CloseDayScheduleTest extends TestCase
{
    public function test_default_closing_time_is_16_30(): void
    {
        $schedule = app(DayClosingSchedule::class);

        $this->assertSame('16:30', $schedule->closingTime());
        $this->assertDatabaseCount('settings', 0);
    }

    public function test_closing_time_can_be_changed_and_persisted(): void
    {
        $schedule = app(DayClosingSchedule::class);

        $schedule->setClosingTime('18:45');

        $this->assertSame('18:45', $schedule->closingTime());
        $this->assertDatabaseHas('settings', [
            'key' => DayClosingSchedule::SETTING_KEY,
            'value' => '18:45',
        ]);
    }

    public function test_closing_time_rejects_an_invalid_format(): void
    {
        $schedule = app(DayClosingSchedule::class);

        try {
            $schedule->setClosingTime('25:00');
            $this->fail('Se esperaba una excepción de validación.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('closing_time', $exception->errors());
        }

        $this->assertDatabaseCount('settings', 0);
    }

    public function test_invalid_stored_value_falls_back_to_the_default(): void
    {
        Setting::query()->create([
            'key' => DayClosingSchedule::SETTING_KEY,
            'value' => 'no-es-una-hora',
        ]);

        $this->assertSame('16:30', app(DayClosingSchedule::class)->closingTime());
    }

    public function test_should_close_now_compares_against_the_configured_time(): void
    {
        $schedule = app(DayClosingSchedule::class);
        $schedule->setClosingTime('18:00');

        $this->assertFalse($schedule->shouldCloseNow(Carbon::parse('2026-11-06 17:59:00')));
        $this->assertTrue($schedule->shouldCloseNow(Carbon::parse('2026-11-06 18:00:00')));
        $this->assertTrue($schedule->shouldCloseNow(Carbon::parse('2026-11-06 23:59:00')));
        $this->assertFalse($schedule->shouldCloseNow(Carbon::parse('2026-11-07 00:00:00')));
    }

    public function test_next_closing_at_is_today_or_tomorrow(): void
    {
        $schedule = app(DayClosingSchedule::class);
        $schedule->setClosingTime('18:00');

        $today = $schedule->nextClosingAt(Carbon::parse('2026-11-06 17:00:00'));
        $tomorrow = $schedule->nextClosingAt(Carbon::parse('2026-11-06 19:00:00'));

        $this->assertSame('2026-11-06 18:00:00', $today->format('Y-m-d H:i:s'));
        $this->assertSame('2026-11-07 18:00:00', $tomorrow->format('Y-m-d H:i:s'));
    }
}
