<?php

namespace Tests\Feature\Hardening;

use App\Domain\Reports\TicketCsvWriter;
use App\Domain\Ticketing\QueueNotifier;
use App\Domain\Ticketing\Ticket;
use Illuminate\Broadcasting\BroadcastManager;
use Illuminate\Support\Facades\Broadcast;
use PHPUnit\Framework\Attributes\Group;
use RuntimeException;
use Tests\Concerns\SeedsTicketMatrix;
use Tests\TestCase;

#[Group('t-hardening')]
class SystemHardeningTest extends TestCase
{
    use SeedsTicketMatrix;

    public function test_responses_include_security_headers(): void
    {
        $this->get('/up')
            ->assertOk()
            ->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertHeader('X-Frame-Options', 'SAMEORIGIN')
            ->assertHeader('Referrer-Policy', 'no-referrer')
            ->assertHeader(
                'Permissions-Policy',
                'camera=(), microphone=(), geolocation=(), payment=()',
            );
    }

    public function test_public_ticket_issuance_is_rate_limited_by_ip(): void
    {
        config(['tickets.rate_limits.tickets_per_minute' => 2]);
        $this->seedMatrix();

        $payload = [
            'kiosk' => $this->kioskFor('plataforma')->token,
            'section' => 'plataforma',
            'category' => 'general',
        ];

        $this->postJson('/api/v1/tickets', $payload)->assertCreated();
        $this->postJson('/api/v1/tickets', $payload)->assertCreated();
        $this->postJson('/api/v1/tickets', $payload)->assertTooManyRequests();

        $this->assertDatabaseCount('tickets', 2);
    }

    public function test_public_display_snapshot_is_rate_limited_by_ip(): void
    {
        config(['tickets.rate_limits.display_per_minute' => 2]);
        $this->seedMatrix();

        $this->getJson('/api/v1/display/snapshot')->assertOk();
        $this->getJson('/api/v1/display/snapshot')->assertOk();
        $this->getJson('/api/v1/display/snapshot')->assertTooManyRequests();
    }

    public function test_csv_export_neutralizes_spreadsheet_formulas(): void
    {
        $this->seedMatrix();

        $ticket = Ticket::factory()->create([
            'station_label' => '=HYPERLINK("http://malicioso")',
            'service_label' => '+SUMA(1,1)',
        ]);

        $row = app(TicketCsvWriter::class)->row($ticket);

        $this->assertSame("'=HYPERLINK(\"http://malicioso\")", $row[10]);
        $this->assertSame("'+SUMA(1,1)", $row[7]);
        $this->assertSame($ticket->id, $row[0]);
    }

    public function test_ticket_issuance_survives_a_broadcast_failure(): void
    {
        $this->seedMatrix();

        $manager = \Mockery::mock(BroadcastManager::class);
        $manager->shouldReceive('queue')
            ->once()
            ->andThrow(new RuntimeException('Reverb no disponible.'));
        Broadcast::swap($manager);

        $this->postJson('/api/v1/tickets', [
            'kiosk' => $this->kioskFor('plataforma')->token,
            'section' => 'plataforma',
            'category' => 'general',
        ])->assertCreated();

        $this->assertDatabaseCount('tickets', 1);
    }

    public function test_queue_notifier_swallows_broadcast_failures(): void
    {
        $manager = \Mockery::mock(BroadcastManager::class);
        $manager->shouldReceive('queue')
            ->once()
            ->andThrow(new RuntimeException('Reverb no disponible.'));
        Broadcast::swap($manager);

        (new QueueNotifier)->notify('plataforma');

        $this->assertSame(0, Ticket::query()->count());
    }

    public function test_health_command_reports_the_system_state(): void
    {
        $this->seedMatrix();

        $this->artisan('tickets:health')
            ->expectsOutputToContain('Base de datos')
            ->expectsOutputToContain('Hora de cierre')
            ->assertSuccessful();
    }
}
