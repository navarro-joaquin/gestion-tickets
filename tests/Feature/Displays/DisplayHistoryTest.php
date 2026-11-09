<?php

namespace Tests\Feature\Displays;

use App\Domain\Displays\Display;
use App\Domain\Sections\Section;
use App\Domain\Ticketing\Ticket;
use App\Domain\Ticketing\TicketStatus;
use Tests\Concerns\SeedsTicketMatrix;
use Tests\TestCase;

class DisplayHistoryTest extends TestCase
{
    use SeedsTicketMatrix;

    public function test_snapshot_returns_the_last_five_called_or_attended_tickets(): void
    {
        $this->seedMatrix();
        $section = $this->section('plataforma');

        foreach (range(1, 6) as $number) {
            $this->resolvedTicket($section, $number, now()->subMinutes(7 - $number));
        }

        $this->calledTicket($section, 7, 'Ventanilla 7', now());

        $this->resolvedTicket($section, 90, now(), TicketStatus::NoShow);
        $this->resolvedTicket($section, 91, now(), TicketStatus::Expired);

        $waiting = Ticket::factory()->for($section)->create([
            'status' => TicketStatus::Waiting,
            'number' => 92,
            'code' => 'P',
        ]);

        $yesterday = $this->resolvedTicket(
            $section,
            93,
            now()->subDay(),
            TicketStatus::Finished,
            now()->subDay()->toDateString(),
        );

        $response = $this->getJson('/api/v1/display/snapshot')->assertOk();

        $response
            ->assertJsonCount(5, 'recent')
            ->assertJsonPath('recent.0.number', 7)
            ->assertJsonPath('recent.0.station_label', 'Ventanilla 7')
            ->assertJsonPath('recent.0.status', TicketStatus::Called->value)
            ->assertJsonPath('recent.1.number', 6)
            ->assertJsonPath('recent.1.station_label', 'Ventanilla 6')
            ->assertJsonPath('recent.4.number', 3);

        $numbers = collect($response->json('recent'))->pluck('number');

        $this->assertFalse($numbers->contains($waiting->number));
        $this->assertFalse($numbers->contains(90));
        $this->assertFalse($numbers->contains(91));
        $this->assertFalse($numbers->contains($yesterday->number));
    }

    public function test_history_is_limited_to_the_sections_assigned_to_the_display(): void
    {
        $this->seedMatrix();
        $plataforma = $this->section('plataforma');
        $odeco = $this->section('odeco');

        $display = Display::factory()->create();
        $display->sections()->sync([$plataforma->id]);

        $this->resolvedTicket($plataforma, 1, now());
        $this->resolvedTicket($odeco, 2, now());

        $this->getJson('/api/v1/display/snapshot?display='.$display->token)
            ->assertOk()
            ->assertJsonCount(1, 'recent')
            ->assertJsonPath('recent.0.number', 1)
            ->assertJsonPath('recent.0.section.slug', 'plataforma');
    }

    private function resolvedTicket(
        Section $section,
        int $number,
        \DateTimeInterface $moment,
        TicketStatus $status = TicketStatus::Finished,
        ?string $periodKey = null,
    ): Ticket {
        return Ticket::factory()->for($section)->create([
            'status' => $status,
            'number' => $number,
            'code' => 'P',
            'station_label' => 'Ventanilla '.$number,
            'period_key' => $periodKey ?? now()->toDateString(),
            'called_at' => $moment,
            'started_at' => $moment,
            'finished_at' => $moment,
        ]);
    }

    private function calledTicket(Section $section, int $number, string $station, \DateTimeInterface $moment): Ticket
    {
        return Ticket::factory()->for($section)->create([
            'status' => TicketStatus::Called,
            'number' => $number,
            'code' => 'P',
            'station_label' => $station,
            'period_key' => now()->toDateString(),
            'called_at' => $moment,
            'call_count' => 1,
        ]);
    }
}
