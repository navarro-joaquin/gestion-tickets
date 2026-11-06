<?php

namespace App\Domain\Sections;

use App\Domain\Operations\Station;
use App\Domain\Services\Service;
use Database\Factories\Domain\Sections\SectionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'slug',
    'name',
    'is_active',
    'sort_order',
    'matrix_confirmed_at',
    'matrix_confirmed_by',
])]
class Section extends Model
{
    /** @use HasFactory<SectionFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'sort_order' => 'integer',
            'matrix_confirmed_at' => 'datetime',
        ];
    }

    /**
     * @return HasMany<SectionTicketCode, $this>
     */
    public function ticketCodes(): HasMany
    {
        return $this->hasMany(SectionTicketCode::class)
            ->orderBy('sort_order')
            ->orderBy('code');
    }

    /**
     * @return HasMany<Station, $this>
     */
    public function stations(): HasMany
    {
        return $this->hasMany(Station::class)
            ->orderBy('sort_order')
            ->orderBy('name');
    }

    /**
     * @return HasMany<Service, $this>
     */
    public function services(): HasMany
    {
        return $this->hasMany(Service::class)
            ->orderBy('sort_order')
            ->orderBy('name');
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    public function isMatrixConfirmed(): bool
    {
        return $this->matrix_confirmed_at !== null;
    }
}
