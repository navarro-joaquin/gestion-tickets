<?php

namespace Tests\Feature\Operations;

use App\Domain\Operations\Actions\OpenOperatorSession;
use App\Domain\Operations\Actions\TransferTicket;
use App\Domain\Operations\Station;
use App\Domain\Ticketing\Actions\CallNextTicket;
use App\Domain\Ticketing\Actions\IssueTicket;
use App\Domain\Ticketing\Actions\StartTicket;
use App\Domain\Ticketing\TicketStatus;
use App\Models\User;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\Group;
use Tests\Concerns\SeedsTicketMatrix;
use Tests\TestCase;

#[Group('t-ops')]
class TicketTransferTest extends TestCase
{
    use SeedsTicketMatrix;

    public function test_a_waiting_ticket_keeps_its_code_and_number_when_transferred(): void
    {
        $this->seedMatrix();

        $ticket = app(IssueTicket::class)->handle('plataforma', 'general');

        $transferred = app(TransferTicket::class)->handle(
            $ticket,
            'odeco',
            null,
            null,
            'Caso mal derivado',
        );

        $this->assertSame('A', $transferred->code);
        $this->assertSame(1, $transferred->number);
        $this->assertSame('A-001', $transferred->label);
        $this->assertSame($this->section('plataforma')->id, $transferred->section_id);
        $this->assertSame($this->section('odeco')->id, $transferred->current_section_id);
        $this->assertSame(TicketStatus::Waiting, $transferred->status);
        $this->assertTrue($transferred->is_transferred);
        $this->assertCount(1, $transferred->transfers);
        $this->assertDatabaseHas('ticket_transfers', [
            'ticket_id' => $ticket->id,
            'from_section_id' => $this->section('plataforma')->id,
            'to_section_id' => $this->section('odeco')->id,
            'reason' => 'Caso mal derivado',
        ]);
        $this->assertDatabaseHas('ticket_events', [
            'ticket_id' => $ticket->id,
            'type' => 'transferred',
        ]);
    }

    public function test_a_called_ticket_returns_to_the_waiting_queue_when_transferred(): void
    {
        $this->seedMatrix();

        $ticket = app(IssueTicket::class)->handle('plataforma', 'general');
        $called = app(CallNextTicket::class)->handle('plataforma', 'Ventanilla 1');

        $transferred = app(TransferTicket::class)->handle($called, 'odeco');

        $this->assertSame(TicketStatus::Waiting, $transferred->status);
        $this->assertSame(0, $transferred->call_count);
        $this->assertNull($transferred->station_label);
        $this->assertNull($transferred->operator_id);
        $this->assertNull($transferred->called_at);
        $this->assertSame(0, $ticket->assignments()->whereNull('released_at')->count());
        $this->assertSame($this->section('odeco')->id, $transferred->current_section_id);
    }

    public function test_an_in_service_ticket_cannot_be_transferred(): void
    {
        $this->seedMatrix();

        $ticket = app(IssueTicket::class)->handle('plataforma', 'general');
        $called = app(CallNextTicket::class)->handle('plataforma', 'Ventanilla 1');
        $started = app(StartTicket::class)->handle($called);

        try {
            app(TransferTicket::class)->handle($started, 'odeco');
            $this->fail('Se esperaba una excepción de validación.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('ticket', $exception->errors());
        }

        $this->assertSame($this->section('plataforma')->id, $started->fresh()->current_section_id);
    }

    public function test_a_ticket_cannot_be_transferred_to_its_current_section(): void
    {
        $this->seedMatrix();

        $ticket = app(IssueTicket::class)->handle('plataforma', 'general');

        try {
            app(TransferTicket::class)->handle($ticket, 'plataforma');
            $this->fail('Se esperaba una excepción de validación.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('section', $exception->errors());
        }
    }

    public function test_the_destination_section_calls_the_transferred_ticket_and_keeps_its_own_numbering(): void
    {
        $this->seedMatrix();

        $issue = app(IssueTicket::class);
        $call = app(CallNextTicket::class);

        $transferred = $issue->handle('plataforma', 'general');
        app(TransferTicket::class)->handle($transferred, 'odeco');

        $called = $call->handle('odeco', 'Ventanilla 1');

        $this->assertNotNull($called);
        $this->assertSame($transferred->id, $called->id);
        $this->assertSame('A-001', $called->label);
        $this->assertSame($this->section('odeco')->id, $called->current_section_id);

        $newTicket = $issue->handle('odeco', 'general');

        $this->assertSame('D-001', $newTicket->label);
        $this->assertSame(1, $newTicket->number);
    }

    public function test_transfer_history_records_operators_and_station_change_keeps_origin(): void
    {
        $this->seedMatrix();

        $fromOperator = User::factory()->create();
        $toOperator = User::factory()->create();
        $station = Station::query()->where('section_id', $this->section('plataforma')->id)->firstOrFail();

        app(OpenOperatorSession::class)->handle($fromOperator->id, 'plataforma', $station->id);

        $ticket = app(IssueTicket::class)->handle('plataforma', 'general');

        $transferred = app(TransferTicket::class)->handle(
            $ticket,
            'odeco',
            $fromOperator->id,
            $toOperator->id,
            'Requiere ODECO',
        );

        $history = $transferred->transfers->first();

        $this->assertSame($fromOperator->id, $history->from_operator_id);
        $this->assertSame($toOperator->id, $history->to_operator_id);
        $this->assertSame('plataforma', $history->fromSection->slug);
        $this->assertSame('odeco', $history->toSection->slug);
        $this->assertSame('plataforma', $transferred->section->slug);
    }
}
