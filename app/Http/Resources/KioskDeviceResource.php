<?php

namespace App\Http\Resources;

use App\Domain\Kiosks\KioskDevice;
use App\Domain\Sections\Section;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin KioskDevice
 */
class KioskDeviceResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'token' => $this->token,
            'is_active' => $this->is_active,
            'last_seen_at' => $this->last_seen_at?->toIso8601String(),
            'sections' => $this->whenLoaded(
                'sections',
                fn (): array => $this->sections
                    ->map(fn (Section $section): array => [
                        'id' => $section->id,
                        'slug' => $section->slug,
                        'name' => $section->name,
                        'is_active' => $section->is_active,
                    ])
                    ->values()
                    ->all(),
            ),
        ];
    }
}
