<?php

namespace App\Http\Resources;

use App\Domain\Operations\OperatorSession;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin OperatorSession
 */
class OperatorSessionResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'operator' => $this->whenLoaded(
                'operator',
                fn (): array => [
                    'id' => $this->operator->id,
                    'name' => $this->operator->name,
                ],
            ),
            'section' => $this->whenLoaded(
                'section',
                fn (): array => [
                    'slug' => $this->section->slug,
                    'name' => $this->section->name,
                ],
            ),
            'station' => $this->whenLoaded(
                'activeAssignment',
                fn (): ?array => $this->activeAssignment ? [
                    'id' => $this->activeAssignment->station->id,
                    'name' => $this->activeAssignment->station->name,
                ] : null,
            ),
            'started_at' => $this->started_at?->toIso8601String(),
            'ended_at' => $this->ended_at?->toIso8601String(),
            'is_active' => $this->isActive(),
            'history' => $this->whenLoaded(
                'assignments',
                fn (): array => $this->assignments
                    ->map(fn ($assignment): array => [
                        'station' => $assignment->station->name,
                        'assigned_at' => $assignment->assigned_at?->toIso8601String(),
                        'released_at' => $assignment->released_at?->toIso8601String(),
                    ])
                    ->values()
                    ->all(),
            ),
        ];
    }
}
