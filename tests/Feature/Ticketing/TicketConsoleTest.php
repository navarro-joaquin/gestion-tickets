<?php

namespace Tests\Feature\Ticketing;

use App\Domain\Ticketing\Actions\CallNextTicket;
use App\Domain\Ticketing\Actions\FinishTicket;
use App\Domain\Ticketing\Actions\IssueTicket;
use App\Domain\Ticketing\Actions\RecallTicket;
use App\Domain\Ticketing\Actions\StartTicket;
use App\Domain\Ticketing\Ticket;
use App\Domain\Ticketing\TicketStatus;
use App\Models\User;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\Group;
use Tests\Concerns\SeedsTicketMatrix;
use Tests\TestCase;

#[Group('t-seq')]
class TicketConsoleTest extends TestCase
{
    use SeedsTicketMatrix;

    public function test_call_next_returns_the_oldest_waiting_ticket_of_the_section(): void
    {
        $this->seedMatrix();
        $issue = app(IssueTicket::class);

        $first = $issue->handle('plataforma', 'general');
        $issue->handle('odeco', 'general');
        $issue->handle('plataforma', 'general');

        $called = app(CallNextTicket::class)->handle('plataforma', 'Ventanilla 1');

        $this->assertNotNull($called);
        $this->assertSame($first->id, $called->id);
        $this->assertSame(TicketStatus::Called, $called->status);
        $this->assertSame('Ventanilla 1', $called->station_label);
        $this->assertSame(1, $called->call_count);
        $this->assertDatabaseHas('ticket_assignments', [
            'ticket_id' => $first->id,
            'station_label' => 'Ventanilla 1',
            'reason' => 'called',
        ]);
        $this->assertDatabaseHas('ticket_events', [
            'ticket_id' => $first->id,
            'type' => 'called',
        ]);
    }

    public function test_call_next_returns_null_when_the_queue_is_empty(): void
    {
        $this->seedMatrix();

        $called = app(CallNextTicket::class)->handle('plataforma', 'Ventanilla 1');

        $this->assertNull($called);
    }

    public function test_call_next_ignores_waiting_tickets_from_previous_days(): void
    {
        $this->seedMatrix();
        $section = $this->section('plataforma');

        $previousDay = Ticket::factory()
            ->forMatrix(
                $section,
                $section->ticketCodes()->where('is_active', true)->firstOrFail(),
                $this->category('general'),
            )
            ->create([
                'period_key' => now()->subDay()->toDateString(),
                'issued_at' => now()->subDay(),
                'status' => TicketStatus::Waiting,
            ]);

        $called = app(CallNextTicket::class)->handle('plataforma', 'Ventanilla 1');

        $this->assertNull($called);
        $this->assertSame(TicketStatus::Waiting, $previousDay->fresh()->status);
    }

    public function test_call_next_takes_todays_ticket_when_a_previous_day_ticket_is_still_waiting(): void
    {
        $this->seedMatrix();
        $section = $this->section('plataforma');

        $previousDay = Ticket::factory()
            ->forMatrix(
                $section,
                $section->ticketCodes()->where('is_active', true)->firstOrFail(),
                $this->category('general'),
            )
            ->create([
                'period_key' => now()->subDay()->toDateString(),
                'issued_at' => now()->subDay(),
                'status' => TicketStatus::Waiting,
            ]);

        $today = app(IssueTicket::class)->handle('plataforma', 'general');

        $called = app(CallNextTicket::class)->handle('plataforma', 'Ventanilla 1');

        $this->assertNotNull($called);
        $this->assertSame($today->id, $called->id);
        $this->assertSame(TicketStatus::Waiting, $previousDay->fresh()->status);
    }

    public function test_a_station_cannot_have_two_active_tickets(): void
    {
        $this->seedMatrix();
        $issue = app(IssueTicket::class);
        $call = app(CallNextTicket::class);

        $issue->handle('plataforma', 'general');
        $call->handle('plataforma', 'Ventanilla 1');
        $issue->handle('plataforma', 'general');

        try {
            $call->handle('plataforma', 'Ventanilla 1');
            $this->fail('Se esperaba una excepción de validación.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('station', $exception->errors());
        }
    }

    public function test_an_operator_cannot_have_two_active_tickets(): void
    {
        $this->seedMatrix();
        $issue = app(IssueTicket::class);
        $call = app(CallNextTicket::class);
        $operator = User::factory()->create();

        $issue->handle('plataforma', 'general');
        $call->handle('plataforma', 'Ventanilla 1', $operator->id);
        $issue->handle('plataforma', 'general');

        try {
            $call->handle('plataforma', 'Ventanilla 2', $operator->id);
            $this->fail('Se esperaba una excepción de validación.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('operator_id', $exception->errors());
        }
    }

    public function test_recall_increments_the_call_count_and_keeps_the_station(): void
    {
        $this->seedMatrix();
        $issue = app(IssueTicket::class);

        $issue->handle('plataforma', 'general');
        $called = app(CallNextTicket::class)->handle('plataforma', 'Ventanilla 1');

        $recalled = app(RecallTicket::class)->handle($called);

        $this->assertSame(TicketStatus::Called, $recalled->status);
        $this->assertSame(2, $recalled->call_count);
        $this->assertSame('Ventanilla 1', $recalled->station_label);
        $this->assertDatabaseHas('ticket_events', [
            'ticket_id' => $called->id,
            'type' => 'recalled',
        ]);
    }

    public function test_recall_is_rejected_after_two_calls(): void
    {
        $this->seedMatrix();
        $issue = app(IssueTicket::class);

        $issue->handle('plataforma', 'general');
        $called = app(CallNextTicket::class)->handle('plataforma', 'Ventanilla 1');
        $recalled = app(RecallTicket::class)->handle($called);

        try {
            app(RecallTicket::class)->handle($recalled);
            $this->fail('Se esperaba una excepción de validación.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('ticket', $exception->errors());
        }
    }

    public function test_recall_is_rejected_for_a_waiting_ticket(): void
    {
        $this->seedMatrix();

        $ticket = app(IssueTicket::class)->handle('plataforma', 'general');

        try {
            app(RecallTicket::class)->handle($ticket);
            $this->fail('Se esperaba una excepción de validación.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('ticket', $exception->errors());
        }
    }

    public function test_start_and_finish_transition_the_ticket_and_free_the_station(): void
    {
        $this->seedMatrix();
        $issue = app(IssueTicket::class);
        $call = app(CallNextTicket::class);

        $issue->handle('plataforma', 'general');
        $called = $call->handle('plataforma', 'Ventanilla 1');

        $started = app(StartTicket::class)->handle($called);

        $this->assertSame(TicketStatus::InService, $started->status);
        $this->assertNotNull($started->started_at);

        $finished = app(FinishTicket::class)->handle($started);

        $this->assertSame(TicketStatus::Finished, $finished->status);
        $this->assertNotNull($finished->finished_at);
        $this->assertNotNull($finished->closed_at);
        $this->assertSame(
            0,
            $finished->assignments()->whereNull('released_at')->count(),
        );

        $issue->handle('plataforma', 'general');

        $next = $call->handle('plataforma', 'Ventanilla 1');

        $this->assertNotNull($next);
        $this->assertSame(TicketStatus::Called, $next->status);
    }

    public function test_finish_requires_the_ticket_to_be_in_service(): void
    {
        $this->seedMatrix();
        $issue = app(IssueTicket::class);

        $issue->handle('plataforma', 'general');
        $called = app(CallNextTicket::class)->handle('plataforma', 'Ventanilla 1');

        try {
            app(FinishTicket::class)->handle($called);
            $this->fail('Se esperaba una excepción de validación.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('ticket', $exception->errors());
        }
    }

    public function test_call_next_rejects_a_section_that_is_not_operational(): void
    {
        $this->seedMatrix();

        $this->section('plataforma')->update(['is_active' => false]);

        try {
            app(CallNextTicket::class)->handle('plataforma', 'Ventanilla 1');
            $this->fail('Se esperaba una excepción de validación.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('section', $exception->errors());
        }

        $this->assertSame(0, Ticket::query()->count());
    }
}
