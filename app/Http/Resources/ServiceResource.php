<?php

namespace App\Http\Resources;

use App\Domain\Services\Service;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Service
 */
class ServiceResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'slug' => $this->slug,
            'name' => $this->name,
            'label' => $this->label,
            'description' => $this->description,
            'is_active' => $this->is_active,
            'is_available_now' => $this->isAvailableAt(now()),
            'available_days' => $this->available_days,
            'available_from' => $this->available_from,
            'available_to' => $this->available_to,
            'effective_from' => $this->effective_from?->toDateString(),
            'effective_to' => $this->effective_to?->toDateString(),
            'sort_order' => $this->sort_order,
            'section' => $this->whenLoaded(
                'section',
                fn (): array => [
                    'slug' => $this->section->slug,
                    'name' => $this->section->name,
                ],
            ),
            'operators' => $this->whenLoaded(
                'operators',
                fn (): array => $this->operators
                    ->map(fn (User $user): array => [
                        'id' => $user->id,
                        'name' => $user->name,
                    ])
                    ->values()
                    ->all(),
            ),
        ];
    }
}
