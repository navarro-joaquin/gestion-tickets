<?php

namespace App\Domain\Ticketing;

use App\Domain\Sections\Section;
use Database\Factories\Domain\Ticketing\TicketSequenceFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'section_id',
    'code',
    'period_key',
    'last_number',
])]
class TicketSequence extends Model
{
    /** @use HasFactory<TicketSequenceFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'period_key' => 'date',
            'last_number' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<Section, $this>
     */
    public function section(): BelongsTo
    {
        return $this->belongsTo(Section::class);
    }
}
