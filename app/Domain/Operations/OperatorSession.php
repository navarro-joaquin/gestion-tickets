<?php

namespace App\Domain\Operations;

use App\Domain\Sections\Section;
use App\Models\User;
use Database\Factories\Domain\Operations\OperatorSessionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

#[Fillable([
    'operator_id',
    'section_id',
    'started_at',
    'ended_at',
])]
class OperatorSession extends Model
{
    /** @use HasFactory<OperatorSessionFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'started_at' => 'datetime',
            'ended_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function operator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'operator_id');
    }

    /**
     * @return BelongsTo<Section, $this>
     */
    public function section(): BelongsTo
    {
        return $this->belongsTo(Section::class);
    }

    /**
     * @return HasMany<StationAssignment, $this>
     */
    public function assignments(): HasMany
    {
        return $this->hasMany(StationAssignment::class)->orderBy('assigned_at');
    }

    /**
     * @return HasOne<StationAssignment, $this>
     */
    public function activeAssignment(): HasOne
    {
        return $this->hasOne(StationAssignment::class)->whereNull('released_at');
    }

    public function isActive(): bool
    {
        return $this->ended_at === null;
    }

    #[Scope]
    protected function active(Builder $query): Builder
    {
        return $query->whereNull('ended_at');
    }
}
