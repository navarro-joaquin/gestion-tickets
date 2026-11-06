<?php

namespace Tests\Feature\Api\V1;

use App\Domain\Ticketing\Actions\IssueTicket;
use PHPUnit\Framework\Attributes\Group;
use Tests\Concerns\SeedsTicketMatrix;
use Tests\TestCase;

#[Group('t-ops')]
class TicketTransferApiTest extends TestCase
{
    use SeedsTicketMatrix;

    public function test_transfer_endpoint_keeps_code_and_number_and_changes_the_current_section(): void
    {
        $this->seedMatrix();

        $issued = $this->postJson('/api/v1/tickets', [
            'section' => 'plataforma',
            'category' => 'general',
        ])->json('data');

        $this->postJson("/api/v1/console/tickets/{$issued['id']}/transfer", [
            'section' => 'odeco',
            'reason' => 'Se dirigió a ODECO',
        ])
            ->assertOk()
            ->assertJsonPath('data.id', $issued['id'])
            ->assertJsonPath('data.code', 'A')
            ->assertJsonPath('data.number', 1)
            ->assertJsonPath('data.label', 'A-001')
            ->assertJsonPath('data.section.slug', 'plataforma')
            ->assertJsonPath('data.current_section.slug', 'odeco')
            ->assertJsonPath('data.is_transferred', true)
            ->assertJsonPath('data.transfers.0.from_section.slug', 'plataforma')
            ->assertJsonPath('data.transfers.0.to_section.slug', 'odeco')
            ->assertJsonPath('data.transfers.0.reason', 'Se dirigió a ODECO')
            ->assertJsonPath('message', 'Ticket transferido a la sección de destino.');
    }

    public function test_display_snapshot_uses_the_current_section_after_a_transfer(): void
    {
        $this->seedMatrix();

        $issue = app(IssueTicket::class);
        $first = $issue->handle('plataforma', 'general');
        $issue->handle('plataforma', 'general');

        $this->postJson("/api/v1/console/tickets/{$first->id}/transfer", [
            'section' => 'odeco',
        ])->assertOk();

        $this->getJson('/api/v1/display/snapshot')
            ->assertOk()
            ->assertJsonPath('data.0.section.slug', 'plataforma')
            ->assertJsonPath('data.0.waiting_count', 1)
            ->assertJsonPath('data.1.section.slug', 'odeco')
            ->assertJsonPath('data.1.waiting_count', 1);
    }

    public function test_reprint_endpoint_returns_the_print_payload_and_records_an_event(): void
    {
        $this->seedMatrix();

        $ticket = app(IssueTicket::class)->handle('plataforma', 'general');

        $response = $this->postJson("/api/v1/console/tickets/{$ticket->id}/reprint")
            ->assertOk()
            ->assertJsonPath('data.id', $ticket->id)
            ->assertJsonPath('message', 'Ticket reimpreso.')
            ->assertJsonStructure(['print' => ['escpos_base64', 'text']]);

        $this->assertStringContainsString('A-001', (string) $response->json('print.text'));

        $this->assertDatabaseHas('ticket_events', [
            'ticket_id' => $ticket->id,
            'type' => 'reprinted',
        ]);
    }

    public function test_print_failure_endpoint_records_the_failure_without_changing_the_status(): void
    {
        $this->seedMatrix();

        $ticket = app(IssueTicket::class)->handle('plataforma', 'general');

        $this->postJson("/api/v1/console/tickets/{$ticket->id}/print-failed", [
            'message' => 'La impresora no responde',
        ])
            ->assertOk()
            ->assertJsonPath('data.status', 'waiting')
            ->assertJsonPath('message', 'Fallo de impresión registrado.');

        $this->assertDatabaseHas('ticket_events', [
            'ticket_id' => $ticket->id,
            'type' => 'print_failed',
        ]);
        $this->assertSame('waiting', $ticket->fresh()->status->value);
    }
}
