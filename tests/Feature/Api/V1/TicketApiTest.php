<?php

namespace Tests\Feature\Api\V1;

use App\Domain\Ticketing\Actions\CallNextTicket;
use App\Domain\Ticketing\Actions\IssueTicket;
use PHPUnit\Framework\Attributes\Group;
use Tests\Concerns\SeedsTicketMatrix;
use Tests\TestCase;

#[Group('t-seq')]
class TicketApiTest extends TestCase
{
    use SeedsTicketMatrix;

    public function test_issue_endpoint_returns_the_ticket_and_the_print_payload(): void
    {
        $this->seedMatrix();

        $response = $this->postJson('/api/v1/tickets', [
            'section' => 'plataforma',
            'category' => 'general',
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.label', 'A-001')
            ->assertJsonPath('data.status', 'waiting')
            ->assertJsonPath('data.section.slug', 'plataforma')
            ->assertJsonPath('data.category.slug', 'general');

        $payload = base64_decode((string) $response->json('print.escpos_base64'), true);

        $this->assertIsString($payload);
        $this->assertStringContainsString('A-001', $payload);
        $this->assertStringContainsString('A-001', (string) $response->json('print.text'));
    }

    public function test_issue_endpoint_validates_the_required_fields(): void
    {
        $this->seedMatrix();

        $this->postJson('/api/v1/tickets', [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['section', 'category']);
    }

    public function test_issue_endpoint_rejects_an_inactive_section(): void
    {
        $this->seedMatrix();

        $this->section('plataforma')->update(['is_active' => false]);

        $this->postJson('/api/v1/tickets', [
            'section' => 'plataforma',
            'category' => 'general',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['section']);
    }

    public function test_console_endpoints_walk_the_ticket_through_its_lifecycle(): void
    {
        $this->seedMatrix();

        $issued = $this->postJson('/api/v1/tickets', [
            'section' => 'plataforma',
            'category' => 'general',
        ])->json('data');

        $this->postJson('/api/v1/console/tickets/next', [
            'section' => 'plataforma',
            'station' => 'Ventanilla 1',
        ])
            ->assertOk()
            ->assertJsonPath('data.id', $issued['id'])
            ->assertJsonPath('data.call_count', 1);

        $this->postJson("/api/v1/console/tickets/{$issued['id']}/recall")
            ->assertOk()
            ->assertJsonPath('data.call_count', 2);

        $this->postJson("/api/v1/console/tickets/{$issued['id']}/start")
            ->assertOk()
            ->assertJsonPath('data.status', 'in_service');

        $this->postJson("/api/v1/console/tickets/{$issued['id']}/finish")
            ->assertOk()
            ->assertJsonPath('data.status', 'finished');
    }

    public function test_console_queue_returns_the_waiting_count_and_the_active_ticket(): void
    {
        $this->seedMatrix();

        $issue = app(IssueTicket::class);
        $issue->handle('plataforma', 'general');
        $issue->handle('plataforma', 'pregnancy');

        $this->getJson('/api/v1/console/queue?section=plataforma&station=Ventanilla%201')
            ->assertOk()
            ->assertJsonPath('data.waiting_count', 2)
            ->assertJsonPath('data.active_ticket', null);

        app(CallNextTicket::class)->handle('plataforma', 'Ventanilla 1');

        $this->getJson('/api/v1/console/queue?section=plataforma&station=Ventanilla%201')
            ->assertOk()
            ->assertJsonPath('data.waiting_count', 1)
            ->assertJsonPath('data.active_ticket.label', 'A-001');
    }

    public function test_next_endpoint_returns_null_when_there_are_no_waiting_tickets(): void
    {
        $this->seedMatrix();

        $this->postJson('/api/v1/console/tickets/next', [
            'section' => 'plataforma',
            'station' => 'Ventanilla 1',
        ])
            ->assertOk()
            ->assertJsonPath('data', null)
            ->assertJsonPath('message', 'No hay tickets en espera para esta sección.');
    }

    public function test_display_snapshot_lists_active_sections_with_their_calls(): void
    {
        $this->seedMatrix();

        $issue = app(IssueTicket::class);
        $issue->handle('plataforma', 'general');
        $issue->handle('plataforma', 'general');

        app(CallNextTicket::class)->handle('plataforma', 'Ventanilla 1');

        $this->getJson('/api/v1/display/snapshot')
            ->assertOk()
            ->assertJsonCount(3, 'data')
            ->assertJsonPath('data.0.section.slug', 'plataforma')
            ->assertJsonPath('data.0.waiting_count', 1)
            ->assertJsonPath('data.0.calling.0.label', 'A-001')
            ->assertJsonPath('data.0.calling.0.status', 'called');
    }
}
