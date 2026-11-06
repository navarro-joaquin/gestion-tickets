<?php

namespace Tests\Feature\Api\V1;

use App\Domain\Settings\DayClosingSchedule;
use App\Domain\Users\UserRole;
use App\Models\User;
use Tests\TestCase;

class AdminSettingsApiTest extends TestCase
{
    public function test_show_returns_the_default_closing_time(): void
    {
        $this->actingAs($this->admin())
            ->getJson('/api/v1/admin/settings')
            ->assertOk()
            ->assertJsonPath('data.closing_time', '16:30')
            ->assertJsonPath('data.timezone', 'America/La_Paz')
            ->assertJsonStructure([
                'data' => ['closing_time', 'next_closing_at', 'timezone', 'retention_months'],
            ])
            ->assertJsonPath('data.retention_months', 12);
    }

    public function test_update_persists_the_closing_time(): void
    {
        $this->actingAs($this->admin())
            ->putJson('/api/v1/admin/settings', [
                'closing_time' => '18:45',
            ])
            ->assertOk()
            ->assertJsonPath('data.closing_time', '18:45')
            ->assertJsonPath('message', 'Configuración actualizada.');

        $this->assertSame('18:45', app(DayClosingSchedule::class)->closingTime());

        $this->getJson('/api/v1/admin/settings')
            ->assertOk()
            ->assertJsonPath('data.closing_time', '18:45');
    }

    public function test_update_rejects_an_invalid_time(): void
    {
        $this->actingAs($this->admin())
            ->putJson('/api/v1/admin/settings', [
                'closing_time' => '25:00',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['closing_time']);

        $this->assertSame('16:30', app(DayClosingSchedule::class)->closingTime());
    }

    public function test_update_requires_the_closing_time(): void
    {
        $this->actingAs($this->admin())
            ->putJson('/api/v1/admin/settings', [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['closing_time']);
    }

    private function admin(): User
    {
        return User::factory()->create(['role' => UserRole::Admin]);
    }
}
