<?php

namespace Database\Factories\Domain\Media;

use App\Domain\Media\MediaAsset;
use App\Domain\Media\Playlist;
use App\Domain\Media\PlaylistItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PlaylistItem>
 */
class PlaylistItemFactory extends Factory
{
    protected $model = PlaylistItem::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'playlist_id' => Playlist::factory(),
            'media_asset_id' => MediaAsset::factory(),
            'position' => fake()->numberBetween(0, 20),
            'duration_seconds' => null,
            'is_active' => true,
        ];
    }
}
