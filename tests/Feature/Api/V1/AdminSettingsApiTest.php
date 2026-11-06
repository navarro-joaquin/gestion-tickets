<?php

namespace Tests\Feature\Api\V1;

use App\Domain\Settings\DayClosingSchedule;
use Tests\TestCase;

class AdminSettingsApiTest extends TestCase
{
    public function test_show_returns_the_default_closing_time(): void
    {
        $this->getJson('/api/v1/admin/settings')
            ->assertOk()
            ->assertJsonPath('data.closing_time', '16:30')
            ->assertJsonPath('data.timezone', 'America/La_Paz')
            ->assertJsonStructure([
                'data' => ['closing_time', 'next_closing_at', 'timezone'],
            ]);
    }

    public function test_update_persists_the_closing_time(): void
    {
        $this->putJson('/api/v1/admin/settings', [
            'closing_time' => '18:45',
        ])
            ->assertOk()
            ->assertJsonPath('data.closing_time', '18:45')
            ->assertJsonPath('message', 'Hora de cierre actualizada.');

        $this->assertSame('18:45', app(DayClosingSchedule::class)->closingTime());

        $this->getJson('/api/v1/admin/settings')
            ->assertOk()
            ->assertJsonPath('data.closing_time', '18:45');
    }

    public function test_update_rejects_an_invalid_time(): void
    {
        $this->putJson('/api/v1/admin/settings', [
            'closing_time' => '25:00',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['closing_time']);

        $this->assertSame('16:30', app(DayClosingSchedule::class)->closingTime());
    }

    public function test_update_requires_the_closing_time(): void
    {
        $this->putJson('/api/v1/admin/settings', [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['closing_time']);
    }
}
