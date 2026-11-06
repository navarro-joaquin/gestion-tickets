<?php

namespace App\Http\Resources;

use App\Domain\Priorities\PriorityProfile;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin PriorityProfile
 */
class PriorityProfileResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'description' => $this->description,
            'general_ratio' => $this->general_ratio,
            'preferential_ratio' => $this->preferential_ratio,
            'aging_minutes' => $this->aging_minutes,
            'max_consecutive_preferential' => $this->max_consecutive_preferential,
            'is_active' => $this->is_active,
            'effective_from' => $this->effective_from?->toDateString(),
            'effective_to' => $this->effective_to?->toDateString(),
            'is_effective_now' => $this->isEffectiveOn(now()),
        ];
    }
}
