<?php

namespace App\Http\Resources;

use App\Domain\Media\MediaAsset;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin MediaAsset
 */
class MediaAssetResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'type' => $this->type,
            'mime' => $this->mime,
            'file_size' => $this->file_size,
            'duration_seconds' => $this->duration_seconds,
            'is_active' => $this->is_active,
            'url' => $this->url(),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
