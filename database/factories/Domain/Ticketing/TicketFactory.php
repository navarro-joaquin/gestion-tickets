<?php

namespace Database\Factories\Domain\Ticketing;

use App\Domain\Sections\Category;
use App\Domain\Sections\Section;
use App\Domain\Sections\SectionTicketCode;
use App\Domain\Ticketing\Ticket;
use App\Domain\Ticketing\TicketStatus;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Ticket>
 */
class TicketFactory extends Factory
{
    protected $model = Ticket::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'section_id' => Section::factory(),
            'section_ticket_code_id' => SectionTicketCode::factory(),
            'category_id' => Category::factory(),
            'code' => 'A',
            'period_key' => now()->toDateString(),
            'number' => fake()->unique()->numberBetween(1, 999999),
            'status' => TicketStatus::Waiting,
            'station_label' => null,
            'operator_id' => null,
            'issued_at' => now(),
            'called_at' => null,
            'call_count' => 0,
            'started_at' => null,
            'finished_at' => null,
            'closed_at' => null,
        ];
    }

    public function forMatrix(Section $section, SectionTicketCode $ticketCode, Category $category): static
    {
        return $this->state(fn (): array => [
            'section_id' => $section->id,
            'section_ticket_code_id' => $ticketCode->id,
            'category_id' => $category->id,
            'code' => $ticketCode->code,
        ]);
    }

    public function called(string $stationLabel = 'Ventanilla 1', ?User $operator = null): static
    {
        return $this->state(fn (): array => [
            'status' => TicketStatus::Called,
            'station_label' => $stationLabel,
            'operator_id' => $operator?->id,
            'called_at' => now(),
            'call_count' => 1,
        ]);
    }

    public function inService(string $stationLabel = 'Ventanilla 1', ?User $operator = null): static
    {
        return $this->state(fn (): array => [
            'status' => TicketStatus::InService,
            'station_label' => $stationLabel,
            'operator_id' => $operator?->id,
            'called_at' => now(),
            'call_count' => 1,
            'started_at' => now(),
        ]);
    }
}
