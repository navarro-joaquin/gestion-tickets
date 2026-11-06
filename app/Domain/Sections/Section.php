<?php

namespace App\Domain\Sections;

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

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    public function isMatrixConfirmed(): bool
    {
        return $this->matrix_confirmed_at !== null;
    }
}
