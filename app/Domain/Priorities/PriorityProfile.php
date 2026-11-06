<?php

namespace App\Domain\Priorities;

use Carbon\CarbonInterface;
use Database\Factories\Domain\Priorities\PriorityProfileFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

#[Fillable([
    'name',
    'description',
    'general_ratio',
    'preferential_ratio',
    'aging_minutes',
    'max_consecutive_preferential',
    'is_active',
    'effective_from',
    'effective_to',
])]
class PriorityProfile extends Model
{
    /** @use HasFactory<PriorityProfileFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'general_ratio' => 'integer',
            'preferential_ratio' => 'integer',
            'aging_minutes' => 'integer',
            'max_consecutive_preferential' => 'integer',
            'is_active' => 'boolean',
            'effective_from' => 'date',
            'effective_to' => 'date',
        ];
    }

    public function isEffectiveOn(CarbonInterface $moment): bool
    {
        if (! $this->is_active) {
            return false;
        }

        $date = $moment->toDateString();

        if ($this->effective_from !== null && $this->effective_from->toDateString() > $date) {
            return false;
        }

        if ($this->effective_to !== null && $this->effective_to->toDateString() < $date) {
            return false;
        }

        return true;
    }

    #[Scope]
    protected function effectiveOn(Builder $query, CarbonInterface $moment): Builder
    {
        $date = $moment->toDateString();

        return $query
            ->where('is_active', true)
            ->where(function (Builder $query) use ($date): void {
                $query->whereNull('effective_from')
                    ->orWhereDate('effective_from', '<=', $date);
            })
            ->where(function (Builder $query) use ($date): void {
                $query->whereNull('effective_to')
                    ->orWhereDate('effective_to', '>=', $date);
            });
    }

    public function totalRatio(): int
    {
        return max(1, $this->general_ratio + $this->preferential_ratio);
    }
}
