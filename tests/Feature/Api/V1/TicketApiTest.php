<?php

namespace Tests\Feature\Api\V1;

use App\Domain\Kiosks\KioskDevice;
use App\Domain\Operations\Station;
use App\Domain\Ticketing\Actions\CallNextTicket;
use App\Domain\Ticketing\Actions\IssueTicket;
use App\Domain\Ticketing\Ticket;
use App\Domain\Ticketing\TicketStatus;
use App\Models\User;
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
            'kiosk' => $this->kioskFor('plataforma')->token,
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
            ->assertJsonValidationErrors(['kiosk', 'section', 'category']);
    }

    public function test_issue_endpoint_rejects_an_inactive_section(): void
    {
        $this->seedMatrix();

        $this->section('plataforma')->update(['is_active' => false]);

        $this->postJson('/api/v1/tickets', [
            'kiosk' => $this->kioskFor('plataforma')->token,
            'section' => 'plataforma',
            'category' => 'general',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['section']);
    }

    public function test_issue_endpoint_requires_an_active_kiosk_token(): void
    {
        $this->seedMatrix();

        $payload = [
            'section' => 'plataforma',
            'category' => 'general',
        ];

        $this->postJson('/api/v1/tickets', $payload)
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['kiosk']);

        $this->postJson('/api/v1/tickets', [
            ...$payload,
            'kiosk' => 'token-inexistente',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['kiosk']);

        $this->postJson('/api/v1/tickets', [
            ...$payload,
            'kiosk' => ['no-es-un-token'],
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['kiosk']);

        $inactive = KioskDevice::factory()->inactive()->create();
        $inactive->sections()->attach($this->section('plataforma')->id);

        $this->postJson('/api/v1/tickets', [
            ...$payload,
            'kiosk' => $inactive->token,
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['kiosk']);

        $this->assertDatabaseCount('tickets', 0);
    }

    public function test_issue_endpoint_rejects_a_section_not_assigned_to_the_kiosk(): void
    {
        $this->seedMatrix();

        $this->postJson('/api/v1/tickets', [
            'kiosk' => $this->kioskFor('odeco')->token,
            'section' => 'plataforma',
            'category' => 'general',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['section']);

        $this->assertDatabaseCount('tickets', 0);
    }

    public function test_console_endpoints_walk_the_ticket_through_its_lifecycle(): void
    {
        $this->seedMatrix();

        $issued = $this->postJson('/api/v1/tickets', [
            'kiosk' => $this->kioskFor('plataforma')->token,
            'section' => 'plataforma',
            'category' => 'general',
        ])->json('data');

        $operator = User::factory()->create();
        $station = $this->station('plataforma');

        $this->actingAs($operator);

        $this->postJson('/api/v1/console/session', [
            'section' => 'plataforma',
            'station_id' => $station->id,
        ])->assertCreated();

        $this->postJson('/api/v1/console/tickets/next', [
            'section' => 'plataforma',
            'station' => $station->name,
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

        $this->actingAs(User::factory()->create())
            ->getJson('/api/v1/console/queue?section=plataforma&station=Ventanilla%201')
            ->assertOk()
            ->assertJsonPath('data.waiting_count', 2)
            ->assertJsonPath('data.active_ticket', null);

        app(CallNextTicket::class)->handle('plataforma', 'Ventanilla 1');

        $this->getJson('/api/v1/console/queue?section=plataforma&station=Ventanilla%201')
            ->assertOk()
            ->assertJsonPath('data.waiting_count', 1)
            ->assertJsonPath('data.active_ticket.label', 'A-001');
    }

    public function test_console_queue_ignores_waiting_tickets_from_previous_days(): void
    {
        $this->seedMatrix();
        $section = $this->section('plataforma');

        Ticket::factory()
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

        app(IssueTicket::class)->handle('plataforma', 'general');

        $this->actingAs(User::factory()->create())
            ->getJson('/api/v1/console/queue?section=plataforma&station=Ventanilla%201')
            ->assertOk()
            ->assertJsonPath('data.waiting_count', 1);
    }

    public function test_console_queue_ignores_active_tickets_from_previous_days(): void
    {
        $this->seedMatrix();
        $section = $this->section('plataforma');

        Ticket::factory()
            ->called('Ventanilla 1')
            ->forMatrix(
                $section,
                $section->ticketCodes()->where('is_active', true)->firstOrFail(),
                $this->category('general'),
            )
            ->create([
                'period_key' => now()->subDay()->toDateString(),
                'issued_at' => now()->subDay(),
                'called_at' => now()->subDay(),
            ]);

        $this->actingAs(User::factory()->create())
            ->getJson('/api/v1/console/queue?section=plataforma&station=Ventanilla%201')
            ->assertOk()
            ->assertJsonPath('data.waiting_count', 0)
            ->assertJsonPath('data.active_ticket', null);
    }

    public function test_next_endpoint_returns_null_when_there_are_no_waiting_tickets(): void
    {
        $this->seedMatrix();

        $operator = User::factory()->create();
        $station = $this->station('plataforma');

        $this->actingAs($operator);

        $this->postJson('/api/v1/console/session', [
            'section' => 'plataforma',
            'station_id' => $station->id,
        ])->assertCreated();

        $this->postJson('/api/v1/console/tickets/next', [
            'section' => 'plataforma',
            'station' => $station->name,
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

    public function test_display_snapshot_ignores_tickets_from_previous_days(): void
    {
        $this->seedMatrix();
        $section = $this->section('plataforma');

        $ticketCode = $section->ticketCodes()->where('is_active', true)->firstOrFail();
        $category = $this->category('general');

        Ticket::factory()
            ->forMatrix($section, $ticketCode, $category)
            ->create([
                'period_key' => now()->subDay()->toDateString(),
                'issued_at' => now()->subDay(),
                'status' => TicketStatus::Waiting,
            ]);

        Ticket::factory()
            ->called('Ventanilla 2')
            ->forMatrix($section, $ticketCode, $category)
            ->create([
                'period_key' => now()->subDay()->toDateString(),
                'issued_at' => now()->subDay(),
                'called_at' => now()->subDay(),
            ]);

        app(IssueTicket::class)->handle('plataforma', 'general');

        $this->getJson('/api/v1/display/snapshot')
            ->assertOk()
            ->assertJsonPath('data.0.waiting_count', 1)
            ->assertJsonCount(0, 'data.0.calling');
    }

    private function station(string $sectionSlug): Station
    {
        return Station::query()
            ->where('section_id', $this->section($sectionSlug)->id)
            ->orderBy('sort_order')
            ->firstOrFail();
    }
}
