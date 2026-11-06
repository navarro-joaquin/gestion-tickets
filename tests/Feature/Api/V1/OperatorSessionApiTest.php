<?php

namespace Tests\Feature\Api\V1;

use App\Domain\Operations\Station;
use App\Domain\Ticketing\Actions\CallNextTicket;
use App\Domain\Ticketing\Actions\IssueTicket;
use App\Domain\Users\UserRole;
use App\Models\User;
use PHPUnit\Framework\Attributes\Group;
use Tests\Concerns\SeedsTicketMatrix;
use Tests\TestCase;

#[Group('t-ops')]
class OperatorSessionApiTest extends TestCase
{
    use SeedsTicketMatrix;

    public function test_operators_endpoint_lists_active_operators_and_supervisors(): void
    {
        $this->seedMatrix();

        $operator = User::factory()->create(['name' => 'Ana Operadora']);
        User::factory()->create(['role' => UserRole::Supervisor, 'name' => 'Luis Supervisor']);
        User::factory()->create(['role' => UserRole::Admin, 'name' => 'Admin']);
        User::factory()->create(['is_active' => false, 'name' => 'Inactivo']);

        $this->actingAs($operator)
            ->getJson('/api/v1/console/operators')
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.name', 'Ana Operadora')
            ->assertJsonPath('data.1.name', 'Luis Supervisor')
            ->assertJsonPath('data.0.id', $operator->id);
    }

    public function test_stations_endpoint_returns_only_the_requested_section(): void
    {
        $this->seedMatrix();

        $this->actingAs(User::factory()->create())
            ->getJson('/api/v1/console/stations?section=plataforma')
            ->assertOk()
            ->assertJsonCount(3, 'data')
            ->assertJsonPath('data.0.name', 'Ventanilla 1')
            ->assertJsonPath('data.1.name', 'Ventanilla 2');

        $this->getJson('/api/v1/console/stations?section=odeco')
            ->assertOk()
            ->assertJsonCount(3, 'data');
    }

    public function test_session_endpoints_open_change_station_and_close(): void
    {
        $this->seedMatrix();

        $operator = User::factory()->create();
        $stations = Station::query()
            ->where('section_id', $this->section('plataforma')->id)
            ->orderBy('sort_order')
            ->get();

        $this->actingAs($operator);

        $created = $this->postJson('/api/v1/console/session', [
            'section' => 'plataforma',
            'station_id' => $stations[0]->id,
        ])
            ->assertCreated()
            ->assertJsonPath('data.operator.id', $operator->id)
            ->assertJsonPath('data.section.slug', 'plataforma')
            ->assertJsonPath('data.station.name', 'Ventanilla 1')
            ->assertJsonPath('data.is_active', true)
            ->json('data');

        $this->getJson('/api/v1/console/queue?section=plataforma&station=Ventanilla%201')
            ->assertOk()
            ->assertJsonPath('data.session.id', $created['id'])
            ->assertJsonPath('data.session.station.name', 'Ventanilla 1');

        $this->patchJson("/api/v1/console/session/{$created['id']}/station", [
            'station_id' => $stations[1]->id,
        ])
            ->assertOk()
            ->assertJsonPath('data.station.name', 'Ventanilla 2')
            ->assertJsonPath('data.is_active', true);

        $this->deleteJson("/api/v1/console/session/{$created['id']}")
            ->assertOk()
            ->assertJsonPath('data.is_active', false)
            ->assertJsonPath('data.station', null)
            ->assertJsonPath('message', 'Sesión de operador cerrada.');
    }

    public function test_open_session_rejects_a_station_from_another_section(): void
    {
        $this->seedMatrix();

        $station = Station::query()
            ->where('section_id', $this->section('odeco')->id)
            ->firstOrFail();

        $this->actingAs(User::factory()->create())
            ->postJson('/api/v1/console/session', [
                'section' => 'plataforma',
                'station_id' => $station->id,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['station_id']);
    }

    public function test_open_session_rejects_a_second_session_for_the_same_operator(): void
    {
        $this->seedMatrix();

        $operator = User::factory()->create();
        $station = Station::query()
            ->where('section_id', $this->section('plataforma')->id)
            ->firstOrFail();

        $this->actingAs($operator)
            ->postJson('/api/v1/console/session', [
                'section' => 'plataforma',
                'station_id' => $station->id,
            ])->assertCreated();

        $this->postJson('/api/v1/console/session', [
            'section' => 'plataforma',
            'station_id' => $station->id,
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['operator_id']);
    }

    public function test_close_session_rejects_an_operator_with_an_active_ticket(): void
    {
        $this->seedMatrix();

        $operator = User::factory()->create();
        $station = Station::query()
            ->where('section_id', $this->section('plataforma')->id)
            ->firstOrFail();

        $this->actingAs($operator);

        $created = $this->postJson('/api/v1/console/session', [
            'section' => 'plataforma',
            'station_id' => $station->id,
        ])->json('data');

        app(IssueTicket::class)->handle('plataforma', 'general');
        app(CallNextTicket::class)->handle('plataforma', $station->name, $operator->id);

        $this->deleteJson("/api/v1/console/session/{$created['id']}")
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['session']);
    }
}
