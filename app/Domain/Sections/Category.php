<?php

namespace App\Domain\Sections;

use Database\Factories\Domain\Sections\CategoryFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

#[Fillable([
    'slug',
    'name',
    'is_preferential',
    'sort_order',
])]
class Category extends Model
{
    /** @use HasFactory<CategoryFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_preferential' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    /**
     * @return BelongsToMany<SectionTicketCode, $this>
     */
    public function ticketCodes(): BelongsToMany
    {
        return $this->belongsToMany(
            SectionTicketCode::class,
            'section_code_category',
            'category_id',
            'section_ticket_code_id',
        )->withTimestamps();
    }
}
