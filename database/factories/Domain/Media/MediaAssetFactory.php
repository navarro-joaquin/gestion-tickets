<?php

namespace Database\Factories\Domain\Media;

use App\Domain\Media\MediaAsset;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MediaAsset>
 */
class MediaAssetFactory extends Factory
{
    protected $model = MediaAsset::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->words(3, true),
            'type' => MediaAsset::TYPE_IMAGE,
            'disk' => 'public',
            'path' => 'media/'.fake()->uuid().'.jpg',
            'mime' => 'image/jpeg',
            'file_size' => fake()->numberBetween(10_000, 2_000_000),
            'duration_seconds' => null,
            'is_active' => true,
        ];
    }

    public function video(): static
    {
        return $this->state(fn (): array => [
            'type' => MediaAsset::TYPE_VIDEO,
            'path' => 'media/'.fake()->uuid().'.mp4',
            'mime' => 'video/mp4',
        ]);
    }
}
