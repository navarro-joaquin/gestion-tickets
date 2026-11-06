<?php

namespace App\Domain\Displays;

use App\Domain\Media\Playlist;
use App\Domain\Sections\Section;
use Database\Factories\Domain\Displays\DisplayFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

#[Fillable([
    'name',
    'slug',
    'token',
    'layout',
    'is_active',
    'last_seen_at',
])]
class Display extends Model
{
    /** @use HasFactory<DisplayFactory> */
    use HasFactory;

    public const LAYOUT_QUEUE = 'queue';

    public const LAYOUT_MIXED = 'mixed';

    public const LAYOUT_MEDIA = 'media';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'last_seen_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsToMany<Section, $this>
     */
    public function sections(): BelongsToMany
    {
        return $this->belongsToMany(Section::class, 'display_section')
            ->orderBy('sections.sort_order')
            ->orderBy('sections.slug');
    }

    /**
     * @return BelongsToMany<Playlist, $this>
     */
    public function playlists(): BelongsToMany
    {
        return $this->belongsToMany(Playlist::class, 'display_playlist')
            ->withPivot('position')
            ->orderBy('display_playlist.position')
            ->orderBy('playlists.name');
    }

    #[Scope]
    protected function active(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }
}
