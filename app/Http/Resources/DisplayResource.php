<?php

namespace App\Http\Resources;

use App\Domain\Displays\Display;
use App\Domain\Media\Playlist;
use App\Domain\Sections\Section;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Display
 */
class DisplayResource extends JsonResource
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
            'layout' => $this->layout,
            'is_active' => $this->is_active,
            'last_seen_at' => $this->last_seen_at?->toIso8601String(),
            'url' => route('display', ['pantalla' => $this->token]),
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
            'playlists' => $this->whenLoaded(
                'playlists',
                fn (): array => $this->playlists
                    ->map(fn (Playlist $playlist): array => [
                        'id' => $playlist->id,
                        'name' => $playlist->name,
                        'is_active' => $playlist->is_active,
                        'items_count' => $playlist->items->count(),
                    ])
                    ->values()
                    ->all(),
            ),
        ];
    }
}
