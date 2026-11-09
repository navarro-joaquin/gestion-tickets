<?php

namespace App\Domain\Kiosks;

use App\Domain\Printing\Printer;
use App\Domain\Sections\Section;
use Database\Factories\Domain\Kiosks\KioskDeviceFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'name',
    'slug',
    'token',
    'is_active',
    'last_seen_at',
])]
class KioskDevice extends Model
{
    /** @use HasFactory<KioskDeviceFactory> */
    use HasFactory;

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
        return $this->belongsToMany(Section::class, 'kiosk_device_section')
            ->orderBy('sections.sort_order')
            ->orderBy('sections.slug');
    }

    /**
     * @return HasMany<Printer, $this>
     */
    public function printers(): HasMany
    {
        return $this->hasMany(Printer::class);
    }

    #[Scope]
    protected function active(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }
}
