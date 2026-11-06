<?php

namespace App\Domain\Operations;

use Database\Factories\Domain\Operations\StationAssignmentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'operator_session_id',
    'station_id',
    'assigned_at',
    'released_at',
])]
class StationAssignment extends Model
{
    /** @use HasFactory<StationAssignmentFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'assigned_at' => 'datetime',
            'released_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<OperatorSession, $this>
     */
    public function operatorSession(): BelongsTo
    {
        return $this->belongsTo(OperatorSession::class);
    }

    /**
     * @return BelongsTo<Station, $this>
     */
    public function station(): BelongsTo
    {
        return $this->belongsTo(Station::class);
    }
}
