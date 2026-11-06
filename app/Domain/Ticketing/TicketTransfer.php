<?php

namespace App\Domain\Ticketing;

use App\Domain\Sections\Section;
use App\Models\User;
use Database\Factories\Domain\Ticketing\TicketTransferFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'ticket_id',
    'from_section_id',
    'to_section_id',
    'from_operator_id',
    'to_operator_id',
    'reason',
    'transferred_at',
])]
class TicketTransfer extends Model
{
    /** @use HasFactory<TicketTransferFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'transferred_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Ticket, $this>
     */
    public function ticket(): BelongsTo
    {
        return $this->belongsTo(Ticket::class);
    }

    /**
     * @return BelongsTo<Section, $this>
     */
    public function fromSection(): BelongsTo
    {
        return $this->belongsTo(Section::class, 'from_section_id');
    }

    /**
     * @return BelongsTo<Section, $this>
     */
    public function toSection(): BelongsTo
    {
        return $this->belongsTo(Section::class, 'to_section_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function fromOperator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'from_operator_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function toOperator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'to_operator_id');
    }
}
