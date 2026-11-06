<?php

namespace App\Http\Resources;

use App\Domain\Printing\Printer;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Printer
 */
class PrinterResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'driver' => $this->driver,
            'target' => $this->target,
            'paper_width' => $this->paper_width,
            'is_active' => $this->is_active,
            'config' => $this->config,
            'station' => $this->whenLoaded(
                'station',
                fn (): ?array => $this->station === null ? null : [
                    'id' => $this->station->id,
                    'name' => $this->station->name,
                    'section' => $this->station->relationLoaded('section') && $this->station->section !== null
                        ? [
                            'slug' => $this->station->section->slug,
                            'name' => $this->station->section->name,
                        ]
                        : null,
                ],
            ),
        ];
    }
}
