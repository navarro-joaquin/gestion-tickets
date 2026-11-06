<?php

namespace App\Domain\Sections;

use Database\Factories\Domain\Sections\SectionTicketCodeFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

#[Fillable([
    'section_id',
    'code',
    'sort_order',
    'is_active',
])]
class SectionTicketCode extends Model
{
    /** @use HasFactory<SectionTicketCodeFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<Section, $this>
     */
    public function section(): BelongsTo
    {
        return $this->belongsTo(Section::class);
    }

    /**
     * @return BelongsToMany<Category, $this>
     */
    public function categories(): BelongsToMany
    {
        return $this->belongsToMany(
            Category::class,
            'section_code_category',
            'section_ticket_code_id',
            'category_id',
        )->withTimestamps()->orderBy('sort_order');
    }
}
