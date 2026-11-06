<?php

namespace Tests\Feature\Ticketing;

use App\Domain\Sections\Category;
use App\Domain\Ticketing\Actions\IssueTicket;
use App\Domain\Ticketing\Ticket;
use App\Events\TicketQueueChanged;
use Carbon\Carbon;
use Illuminate\Support\Facades\Event;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\Group;
use Tests\Concerns\SeedsTicketMatrix;
use Tests\TestCase;

#[Group('t-seq')]
class TicketIssuanceTest extends TestCase
{
    use SeedsTicketMatrix;

    public function test_issue_assigns_daily_numbers_per_section_and_code(): void
    {
        $this->seedMatrix();
        $action = app(IssueTicket::class);

        $first = $action->handle('plataforma', 'general');
        $second = $action->handle('plataforma', 'general');
        $odeco = $action->handle('odeco', 'general');
        $pregnancy = $action->handle('plataforma', 'pregnancy');

        $this->assertSame('A-001', $first->label);
        $this->assertSame('A-002', $second->label);
        $this->assertSame('D-001', $odeco->label);
        $this->assertSame('C-001', $pregnancy->label);
        $this->assertSame(2, $second->number);
    }

    public function test_preferential_categories_share_code_but_keep_their_category(): void
    {
        $this->seedMatrix();
        $action = app(IssueTicket::class);

        $elderly = $action->handle('plataforma', 'elderly');
        $disability = $action->handle('plataforma', 'disability');

        $this->assertSame('B', $elderly->code);
        $this->assertSame('B', $disability->code);
        $this->assertSame('B-001', $elderly->label);
        $this->assertSame('B-002', $disability->label);
        $this->assertSame('elderly', $elderly->category->slug);
        $this->assertSame('disability', $disability->category->slug);
    }

    public function test_numbering_resets_at_midnight_in_the_application_timezone(): void
    {
        $this->seedMatrix();
        $action = app(IssueTicket::class);

        $this->travelTo(Carbon::parse('2026-11-06 23:59:00'));
        $beforeMidnight = $action->handle('plataforma', 'general');

        $this->travelTo(Carbon::parse('2026-11-07 00:01:00'));
        $afterMidnight = $action->handle('plataforma', 'general');

        $this->assertSame(1, $beforeMidnight->number);
        $this->assertSame(1, $afterMidnight->number);
        $this->assertSame('2026-11-06', $beforeMidnight->period_key->toDateString());
        $this->assertSame('2026-11-07', $afterMidnight->period_key->toDateString());
    }

    public function test_numbers_are_not_reused_after_a_ticket_is_deleted(): void
    {
        $this->seedMatrix();
        $action = app(IssueTicket::class);

        $first = $action->handle('plataforma', 'general');
        $first->delete();

        $second = $action->handle('plataforma', 'general');

        $this->assertSame(1, $first->number);
        $this->assertSame(2, $second->number);
    }

    public function test_inactive_section_cannot_issue_tickets(): void
    {
        $this->seedMatrix();

        $this->section('plataforma')->update(['is_active' => false]);

        try {
            app(IssueTicket::class)->handle('plataforma', 'general');
            $this->fail('Se esperaba una excepción de validación.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('section', $exception->errors());
        }

        $this->assertDatabaseCount('tickets', 0);
    }

    public function test_section_with_an_invalid_matrix_cannot_issue_tickets(): void
    {
        $this->seedMatrix();

        $this->section('plataforma')
            ->ticketCodes()
            ->where('code', 'B')
            ->sole()
            ->update(['is_active' => false]);

        try {
            app(IssueTicket::class)->handle('plataforma', 'general');
            $this->fail('Se esperaba una excepción de validación.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('section', $exception->errors());
        }

        $this->assertDatabaseCount('tickets', 0);
    }

    public function test_category_without_a_code_in_the_section_is_rejected(): void
    {
        $this->seedMatrix();

        Category::factory()->create(['slug' => 'exclusive', 'name' => 'Servicio exclusivo']);

        try {
            app(IssueTicket::class)->handle('plataforma', 'exclusive');
            $this->fail('Se esperaba una excepción de validación.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('category', $exception->errors());
        }

        $this->assertDatabaseCount('tickets', 0);
    }

    public function test_queue_change_event_is_dispatched_after_issuing(): void
    {
        $this->seedMatrix();
        Event::fake([TicketQueueChanged::class]);

        app(IssueTicket::class)->handle('plataforma', 'general');

        Event::assertDispatched(
            TicketQueueChanged::class,
            fn (TicketQueueChanged $event): bool => $event->sectionSlug === 'plataforma',
        );
    }

    public function test_issued_ticket_records_an_issued_event(): void
    {
        $this->seedMatrix();

        $ticket = app(IssueTicket::class)->handle('plataforma', 'general');

        $this->assertDatabaseHas('ticket_events', [
            'ticket_id' => $ticket->id,
            'type' => 'issued',
        ]);
        $this->assertSame(1, Ticket::query()->count());
    }
}
