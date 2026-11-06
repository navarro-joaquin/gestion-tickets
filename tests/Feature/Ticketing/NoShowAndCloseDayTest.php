<?php

namespace Tests\Feature\Ticketing;

use App\Domain\Ticketing\Actions\CallNextTicket;
use App\Domain\Ticketing\Actions\IssueTicket;
use App\Domain\Ticketing\Actions\RecallTicket;
use App\Domain\Ticketing\Actions\StartTicket;
use App\Domain\Ticketing\Ticket;
use App\Domain\Ticketing\TicketStatus;
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

    public function test_close_day_expires_waiting_tickets_and_finishes_active_tickets(): void
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

        $this->artisan('tickets:day:close')->assertExitCode(0);

        $this->assertSame(TicketStatus::Finished, $inService->fresh()->status);
        $this->assertSame(TicketStatus::Finished, $called->fresh()->status);
        $this->assertSame(TicketStatus::Expired, $waiting->fresh()->status);
        $this->assertNotNull($waiting->fresh()->closed_at);
        $this->assertSame(0, $called->assignments()->whereNull('released_at')->count());
        $this->assertDatabaseHas('ticket_events', [
            'ticket_id' => $waiting->id,
            'type' => 'expired',
        ]);
    }

    public function test_close_day_does_not_touch_tickets_from_another_period(): void
    {
        $this->seedMatrix();

        $yesterdayTicket = Ticket::factory()->create([
            'period_key' => now()->subDay()->toDateString(),
            'status' => TicketStatus::Waiting,
        ]);

        $this->artisan('tickets:day:close')->assertExitCode(0);

        $this->assertSame(TicketStatus::Waiting, $yesterdayTicket->fresh()->status);
    }
}
