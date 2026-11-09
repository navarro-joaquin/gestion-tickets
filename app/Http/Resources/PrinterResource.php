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
            'kiosk_device' => $this->whenLoaded(
                'kioskDevice',
                fn (): ?array => $this->kioskDevice === null ? null : [
                    'id' => $this->kioskDevice->id,
                    'name' => $this->kioskDevice->name,
                    'slug' => $this->kioskDevice->slug,
                ],
            ),
        ];
    }
}
