<?php

namespace Tests\Feature\Ticketing;

use App\Domain\Settings\DayClosingSchedule;
use App\Domain\Settings\NoShowPolicy;
use App\Domain\Ticketing\Actions\CallNextTicket;
use App\Domain\Ticketing\Actions\IssueTicket;
use App\Domain\Ticketing\Actions\RecallTicket;
use App\Domain\Ticketing\Actions\StartTicket;
use App\Domain\Ticketing\Ticket;
use App\Domain\Ticketing\TicketStatus;
use Carbon\Carbon;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\Group;
use Tests\Concerns\SeedsTicketMatrix;
use Tests\TestCase;

#[Group('t-seq')]
class NoShowAndCloseDayTest extends TestCase
{
    use SeedsTicketMatrix;

    public function test_no_show_command_closes_a_ticket_after_two_calls_and_one_minute(): void
    {
        $this->seedMatrix();
        $issue = app(IssueTicket::class);

        $issue->handle('plataforma', 'general');
        $called = app(CallNextTicket::class)->handle('plataforma', 'Ventanilla 1');
        $recalled = app(RecallTicket::class)->handle($called);

        $this->travel(61)->seconds();

        $this->artisan('tickets:no-show')->assertExitCode(0);

        $ticket = $recalled->fresh();

        $this->assertSame(TicketStatus::NoShow, $ticket->status);
        $this->assertNotNull($ticket->closed_at);
        $this->assertDatabaseHas('ticket_events', [
            'ticket_id' => $ticket->id,
            'type' => 'no_show',
        ]);
        $this->assertSame(0, $ticket->assignments()->whereNull('released_at')->count());
    }

    public function test_no_show_command_keeps_a_recently_recalled_ticket(): void
    {
        $this->seedMatrix();
        $issue = app(IssueTicket::class);

        $issue->handle('plataforma', 'general');
        $called = app(CallNextTicket::class)->handle('plataforma', 'Ventanilla 1');
        $recalled = app(RecallTicket::class)->handle($called);

        $this->travel(30)->seconds();

        $this->artisan('tickets:no-show')->assertExitCode(0);

        $this->assertSame(TicketStatus::Called, $recalled->fresh()->status);
    }

    public function test_no_show_command_ignores_a_ticket_called_only_once(): void
    {
        $this->seedMatrix();
        $issue = app(IssueTicket::class);

        $issue->handle('plataforma', 'general');
        $called = app(CallNextTicket::class)->handle('plataforma', 'Ventanilla 1');

        $this->travel(2)->minutes();

        $this->artisan('tickets:no-show')->assertExitCode(0);

        $this->assertSame(TicketStatus::Called, $called->fresh()->status);
    }

    public function test_close_day_expires_waiting_tickets_marks_called_as_no_show_and_finishes_in_service(): void
    {
        $this->seedMatrix();
        $issue = app(IssueTicket::class);
        $call = app(CallNextTicket::class);

        $issue->handle('plataforma', 'general');
        $inService = $call->handle('plataforma', 'Ventanilla 1');
        $inService = app(StartTicket::class)->handle($inService);

        $issue->handle('plataforma', 'general');
        $called = $call->handle('plataforma', 'Ventanilla 2');

        $waiting = $issue->handle('plataforma', 'general');

        $this->artisan('tickets:day:close', ['--force' => true])->assertExitCode(0);

        $this->assertSame(TicketStatus::Finished, $inService->fresh()->status);
        $this->assertSame(TicketStatus::NoShow, $called->fresh()->status);
        $this->assertSame(TicketStatus::Expired, $waiting->fresh()->status);
        $this->assertNotNull($inService->fresh()->closed_at);
        $this->assertNotNull($called->fresh()->closed_at);
        $this->assertNotNull($waiting->fresh()->closed_at);
        $this->assertSame(0, $called->assignments()->whereNull('released_at')->count());
        $this->assertSame(0, $inService->assignments()->whereNull('released_at')->count());
        $this->assertDatabaseHas('ticket_events', [
            'ticket_id' => $waiting->id,
            'type' => 'expired',
        ]);
        $this->assertDatabaseHas('ticket_events', [
            'ticket_id' => $called->id,
            'type' => 'no_show',
        ]);
        $this->assertDatabaseHas('ticket_events', [
            'ticket_id' => $inService->id,
            'type' => 'finished',
        ]);
    }

    public function test_close_day_does_not_touch_tickets_from_a_future_period(): void
    {
        $this->seedMatrix();

        $tomorrowTicket = Ticket::factory()->create([
            'period_key' => now()->addDay()->toDateString(),
            'status' => TicketStatus::Waiting,
        ]);

        $this->artisan('tickets:day:close', ['--force' => true])->assertExitCode(0);

        $this->assertSame(TicketStatus::Waiting, $tomorrowTicket->fresh()->status);
    }

    public function test_close_day_command_closes_stale_tickets_from_previous_days_before_closing_time(): void
    {
        $this->seedMatrix();
        $yesterday = now()->subDay()->setTime(11, 0);
        $issue = app(IssueTicket::class);

        $issue->handle('plataforma', 'general', null, $yesterday);
        $staleInService = app(CallNextTicket::class)->handle('plataforma', 'Ventanilla 1', null, $yesterday);
        $staleInService = app(StartTicket::class)->handle($staleInService, $yesterday);
        $staleWaiting = $issue->handle('plataforma', 'general', null, $yesterday);

        $todayWaiting = $issue->handle('plataforma', 'general');

        $this->artisan('tickets:day:close')->assertExitCode(0);

        $this->assertSame(TicketStatus::Finished, $staleInService->fresh()->status);
        $this->assertSame(TicketStatus::Expired, $staleWaiting->fresh()->status);
        $this->assertSame(TicketStatus::Waiting, $todayWaiting->fresh()->status);

        $called = app(CallNextTicket::class)->handle('plataforma', 'Ventanilla 1');

        $this->assertSame($todayWaiting->id, $called->id);
    }

    public function test_tickets_cannot_be_issued_after_the_closing_time(): void
    {
        $this->seedMatrix();

        app(DayClosingSchedule::class)->setClosingTime('16:30');

        $this->travelTo(now()->setTime(16, 30));

        try {
            app(IssueTicket::class)->handle('plataforma', 'general');
            $this->fail('Se esperaba que la emisión fuera rechazada tras el cierre.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('section', $exception->errors());
        }

        $this->assertSame(0, Ticket::query()->count());
    }

    public function test_tickets_can_be_issued_right_before_the_closing_time(): void
    {
        $this->seedMatrix();

        app(DayClosingSchedule::class)->setClosingTime('16:30');

        $this->travelTo(now()->setTime(16, 29));

        $ticket = app(IssueTicket::class)->handle('plataforma', 'general');

        $this->assertSame(TicketStatus::Waiting, $ticket->status);
    }

    public function test_no_show_command_uses_the_configured_calls_and_minutes(): void
    {
        $this->seedMatrix();
        $policy = app(NoShowPolicy::class);
        $policy->setCalls(1);
        $policy->setMinutes(5);

        app(IssueTicket::class)->handle('plataforma', 'general');
        $called = app(CallNextTicket::class)->handle('plataforma', 'Ventanilla 1');

        $this->travel(4)->minutes();
        $this->artisan('tickets:no-show')->assertExitCode(0);
        $this->assertSame(TicketStatus::Called, $called->fresh()->status);

        $this->travel(1)->minutes();
        $this->artisan('tickets:no-show')->assertExitCode(0);
        $this->assertSame(TicketStatus::NoShow, $called->fresh()->status);
    }

    public function test_recall_respects_the_configured_maximum_calls(): void
    {
        $this->seedMatrix();
        app(NoShowPolicy::class)->setCalls(3);

        app(IssueTicket::class)->handle('plataforma', 'general');
        $called = app(CallNextTicket::class)->handle('plataforma', 'Ventanilla 1');
        $recall = app(RecallTicket::class);

        $recall->handle($called);
        $ticket = $recall->handle($called);

        $this->assertSame(3, $ticket->fresh()->call_count);

        $this->expectException(ValidationException::class);
        $recall->handle($called);
    }

    public function test_close_day_waits_for_the_configured_time(): void
    {
        $this->seedMatrix();

        $schedule = app(DayClosingSchedule::class);
        $schedule->setClosingTime('18:00');

        $this->travelTo(Carbon::parse('2026-11-06 17:59:00'));

        $waiting = app(IssueTicket::class)->handle('plataforma', 'general');

        $this->artisan('tickets:day:close')->assertExitCode(0);

        $this->assertSame(TicketStatus::Waiting, $waiting->fresh()->status);
    }

    public function test_close_day_runs_at_the_configured_time_without_force(): void
    {
        $this->seedMatrix();

        $schedule = app(DayClosingSchedule::class);
        $schedule->setClosingTime('18:00');

        $this->travelTo(Carbon::parse('2026-11-06 17:59:00'));

        $waiting = app(IssueTicket::class)->handle('plataforma', 'general');

        $this->travelTo(Carbon::parse('2026-11-06 18:00:00'));

        $this->artisan('tickets:day:close')->assertExitCode(0);

        $this->assertSame(TicketStatus::Expired, $waiting->fresh()->status);
    }

    public function test_force_flag_closes_the_day_before_the_configured_time(): void
    {
        $this->seedMatrix();

        $schedule = app(DayClosingSchedule::class);
        $schedule->setClosingTime('18:00');

        $this->travelTo(Carbon::parse('2026-11-06 10:00:00'));

        $waiting = app(IssueTicket::class)->handle('plataforma', 'general');

        $this->artisan('tickets:day:close', ['--force' => true])->assertExitCode(0);

        $this->assertSame(TicketStatus::Expired, $waiting->fresh()->status);
    }
}
