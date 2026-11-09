<?php

namespace Tests\Feature\Api\V1;

use App\Domain\Operations\Station;
use App\Domain\Ticketing\Actions\IssueTicket;
use App\Models\User;
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
            'kiosk' => $this->kioskFor('plataforma')->token,
            'section' => 'plataforma',
            'category' => 'general',
        ])->json('data');

        $this->openSession('plataforma');

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

        $this->openSession('plataforma');

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

    private function openSession(string $sectionSlug): User
    {
        $operator = User::factory()->create();
        $station = Station::query()
            ->where('section_id', $this->section($sectionSlug)->id)
            ->orderBy('sort_order')
            ->firstOrFail();

        $this->actingAs($operator);

        $this->postJson('/api/v1/console/session', [
            'section' => $sectionSlug,
            'station_id' => $station->id,
        ])->assertCreated();

        return $operator;
    }
}
