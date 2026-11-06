<?php

namespace Tests\Feature\Operations;

use App\Domain\Operations\Actions\ChangeOperatorStation;
use App\Domain\Operations\Actions\CloseOperatorSession;
use App\Domain\Operations\Actions\OpenOperatorSession;
use App\Domain\Operations\Station;
use App\Domain\Ticketing\Actions\CallNextTicket;
use App\Domain\Ticketing\Actions\IssueTicket;
use App\Domain\Ticketing\TicketStatus;
use App\Models\User;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\Group;
use Tests\Concerns\SeedsTicketMatrix;
use Tests\TestCase;

#[Group('t-ops')]
class OperatorSessionTest extends TestCase
{
    use SeedsTicketMatrix;

    public function test_open_session_assigns_a_station_and_prevents_two_active_sessions(): void
    {
        $this->seedMatrix();

        $operator = User::factory()->create();
        $section = $this->section('plataforma');
        $station = Station::query()->where('section_id', $section->id)->orderBy('sort_order')->firstOrFail();

        $session = app(OpenOperatorSession::class)->handle($operator->id, 'plataforma', $station->id);

        $this->assertTrue($session->isActive());
        $this->assertSame($station->id, $session->activeAssignment?->station_id);
        $this->assertDatabaseHas('station_assignments', [
            'operator_session_id' => $session->id,
            'station_id' => $station->id,
            'released_at' => null,
        ]);

        try {
            app(OpenOperatorSession::class)->handle($operator->id, 'plataforma', $station->id);
            $this->fail('Se esperaba una excepción de validación.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('operator_id', $exception->errors());
        }
    }

    public function test_a_station_cannot_be_assigned_to_two_open_sessions(): void
    {
        $this->seedMatrix();

        $section = $this->section('plataforma');
        $station = Station::query()->where('section_id', $section->id)->orderBy('sort_order')->firstOrFail();
        $open = app(OpenOperatorSession::class);

        $open->handle(User::factory()->create()->id, 'plataforma', $station->id);

        try {
            $open->handle(User::factory()->create()->id, 'plataforma', $station->id);
            $this->fail('Se esperaba una excepción de validación.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('station_id', $exception->errors());
        }
    }

    public function test_change_station_releases_the_previous_assignment_and_keeps_history(): void
    {
        $this->seedMatrix();

        $operator = User::factory()->create();
        $section = $this->section('plataforma');
        $stations = Station::query()
            ->where('section_id', $section->id)
            ->orderBy('sort_order')
            ->get();

        $session = app(OpenOperatorSession::class)->handle(
            $operator->id,
            'plataforma',
            $stations[0]->id,
        );

        $session = app(ChangeOperatorStation::class)->handle($session, $stations[1]->id);

        $this->assertSame($stations[1]->id, $session->activeAssignment?->station_id);
        $this->assertSame(2, $session->assignments()->count());
        $this->assertSame(1, $session->assignments()->whereNotNull('released_at')->count());
        $this->assertSame(1, $session->assignments()->whereNull('released_at')->count());
    }

    public function test_change_station_rejects_a_station_from_another_section(): void
    {
        $this->seedMatrix();

        $section = $this->section('plataforma');
        $otherStation = Station::query()->where('section_id', $this->section('odeco')->id)->firstOrFail();

        $session = app(OpenOperatorSession::class)->handle(
            User::factory()->create()->id,
            'plataforma',
            Station::query()->where('section_id', $section->id)->firstOrFail()->id,
        );

        try {
            app(ChangeOperatorStation::class)->handle($session, $otherStation->id);
            $this->fail('Se esperaba una excepción de validación.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('station_id', $exception->errors());
        }
    }

    public function test_close_session_releases_the_station(): void
    {
        $this->seedMatrix();

        $session = app(OpenOperatorSession::class)->handle(
            User::factory()->create()->id,
            'plataforma',
            Station::query()->where('section_id', $this->section('plataforma')->id)->firstOrFail()->id,
        );

        $closed = app(CloseOperatorSession::class)->handle($session);

        $this->assertFalse($closed->isActive());
        $this->assertNotNull($closed->ended_at);
        $this->assertSame(0, $closed->assignments()->whereNull('released_at')->count());
    }

    public function test_close_session_rejects_an_operator_with_an_active_ticket(): void
    {
        $this->seedMatrix();

        $operator = User::factory()->create();
        $station = Station::query()->where('section_id', $this->section('plataforma')->id)->firstOrFail();

        $session = app(OpenOperatorSession::class)->handle($operator->id, 'plataforma', $station->id);

        $ticket = app(IssueTicket::class)->handle('plataforma', 'general');
        app(CallNextTicket::class)->handle('plataforma', $station->name, $operator->id);

        try {
            app(CloseOperatorSession::class)->handle($session);
            $this->fail('Se esperaba una excepción de validación.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('session', $exception->errors());
        }

        $this->assertSame($ticket->id, $ticket->fresh()->id);
    }

    public function test_call_next_enforces_the_session_section_and_assigned_station(): void
    {
        $this->seedMatrix();

        $operator = User::factory()->create();
        $station = Station::query()->where('section_id', $this->section('plataforma')->id)->firstOrFail();
        $otherStation = Station::query()
            ->where('section_id', $this->section('plataforma')->id)
            ->whereKeyNot($station->id)
            ->firstOrFail();

        app(OpenOperatorSession::class)->handle($operator->id, 'plataforma', $station->id);
        $issue = app(IssueTicket::class);
        $issue->handle('odeco', 'general');
        $issue->handle('plataforma', 'general');

        try {
            app(CallNextTicket::class)->handle('odeco', $station->name, $operator->id);
            $this->fail('Se esperaba una excepción de validación.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('section', $exception->errors());
        }

        try {
            app(CallNextTicket::class)->handle('plataforma', $otherStation->name, $operator->id);
            $this->fail('Se esperaba una excepción de validación.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('station', $exception->errors());
        }

        $called = app(CallNextTicket::class)->handle('plataforma', $station->name, $operator->id);

        $this->assertNotNull($called);
        $this->assertSame('A-001', $called->label);
    }

    public function test_two_sections_can_use_the_same_station_name_at_the_same_time(): void
    {
        $this->seedMatrix();

        $issue = app(IssueTicket::class);
        $call = app(CallNextTicket::class);

        $issue->handle('plataforma', 'general');
        $platformTicket = $call->handle('plataforma', 'Ventanilla 1');

        $issue->handle('odeco', 'general');
        $odecoTicket = $call->handle('odeco', 'Ventanilla 1');

        $this->assertNotNull($platformTicket);
        $this->assertNotNull($odecoTicket);
        $this->assertSame('A-001', $platformTicket->label);
        $this->assertSame('D-001', $odecoTicket->label);
        $this->assertSame(TicketStatus::Called, $platformTicket->fresh()->status);
        $this->assertSame(TicketStatus::Called, $odecoTicket->fresh()->status);
    }

    public function test_day_close_closes_open_operator_sessions(): void
    {
        $this->seedMatrix();

        $session = app(OpenOperatorSession::class)->handle(
            User::factory()->create()->id,
            'plataforma',
            Station::query()->where('section_id', $this->section('plataforma')->id)->firstOrFail()->id,
        );

        $this->artisan('tickets:day:close', ['--force' => true])->assertExitCode(0);

        $this->assertNotNull($session->fresh()->ended_at);
        $this->assertSame(0, $session->assignments()->whereNull('released_at')->count());
    }
}
