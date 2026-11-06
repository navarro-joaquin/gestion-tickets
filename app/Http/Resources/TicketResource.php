<?php

namespace App\Http\Resources;

use App\Domain\Ticketing\Ticket;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Ticket
 */
class TicketResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'code' => $this->code,
            'number' => $this->number,
            'display_number' => $this->display_number,
            'label' => $this->label,
            'status' => $this->status->value,
            'status_label' => $this->status->label(),
            'station_label' => $this->station_label,
            'call_count' => $this->call_count,
            'issued_at' => $this->issued_at?->toIso8601String(),
            'called_at' => $this->called_at?->toIso8601String(),
            'started_at' => $this->started_at?->toIso8601String(),
            'finished_at' => $this->finished_at?->toIso8601String(),
            'closed_at' => $this->closed_at?->toIso8601String(),
            'section' => $this->whenLoaded(
                'section',
                fn (): array => [
                    'slug' => $this->section->slug,
                    'name' => $this->section->name,
                ],
            ),
            'category' => $this->whenLoaded(
                'category',
                fn (): array => [
                    'slug' => $this->category->slug,
                    'name' => $this->category->name,
                    'is_preferential' => $this->category->is_preferential,
                ],
            ),
        ];
    }
}
