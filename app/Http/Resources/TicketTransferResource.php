<?php

namespace App\Http\Resources;

use App\Domain\Ticketing\TicketTransfer;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin TicketTransfer
 */
class TicketTransferResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'from_section' => $this->whenLoaded(
                'fromSection',
                fn (): array => [
                    'slug' => $this->fromSection->slug,
                    'name' => $this->fromSection->name,
                ],
            ),
            'to_section' => $this->whenLoaded(
                'toSection',
                fn (): array => [
                    'slug' => $this->toSection->slug,
                    'name' => $this->toSection->name,
                ],
            ),
            'from_operator' => $this->whenLoaded(
                'fromOperator',
                fn (): ?array => $this->fromOperator ? [
                    'id' => $this->fromOperator->id,
                    'name' => $this->fromOperator->name,
                ] : null,
            ),
            'to_operator' => $this->whenLoaded(
                'toOperator',
                fn (): ?array => $this->toOperator ? [
                    'id' => $this->toOperator->id,
                    'name' => $this->toOperator->name,
                ] : null,
            ),
            'reason' => $this->reason,
            'transferred_at' => $this->transferred_at?->toIso8601String(),
        ];
    }
}
