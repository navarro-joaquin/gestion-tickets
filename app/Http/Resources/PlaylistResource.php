<?php

namespace App\Http\Resources;

use App\Domain\Media\Playlist;
use App\Domain\Media\PlaylistItem;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Playlist
 */
class PlaylistResource extends JsonResource
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
            'is_active' => $this->is_active,
            'items' => $this->whenLoaded(
                'items',
                fn (): array => $this->items
                    ->map(fn (PlaylistItem $item): array => [
                        'id' => $item->id,
                        'position' => $item->position,
                        'duration_seconds' => $item->duration_seconds,
                        'is_active' => $item->is_active,
                        'media' => $item->mediaAsset === null
                            ? null
                            : new MediaAssetResource($item->mediaAsset),
                    ])
                    ->values()
                    ->all(),
            ),
        ];
    }
}
