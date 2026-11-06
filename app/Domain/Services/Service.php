<?php

namespace App\Domain\Services;

use App\Domain\Sections\Section;
use App\Models\User;
use Carbon\CarbonInterface;
use Database\Factories\Domain\Services\ServiceFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

#[Fillable([
    'section_id',
    'slug',
    'name',
    'label',
    'description',
    'is_active',
    'available_days',
    'available_from',
    'available_to',
    'effective_from',
    'effective_to',
    'sort_order',
])]
class Service extends Model
{
    /** @use HasFactory<ServiceFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'available_days' => 'array',
            'effective_from' => 'date',
            'effective_to' => 'date',
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
     * @return BelongsToMany<User, $this>
     */
    public function operators(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'service_user')
            ->withTimestamps();
    }

    public function isAvailableAt(CarbonInterface $moment): bool
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

        $days = $this->available_days;

        if (is_array($days) && $days !== [] && ! in_array($moment->isoWeekday(), array_map('intval', $days), true)) {
            return false;
        }

        $time = $moment->format('H:i:s');
        $from = $this->available_from;
        $to = $this->available_to;

        if ($from !== null && $to !== null) {
            return $from <= $to
                ? $time >= $from && $time <= $to
                : $time >= $from || $time <= $to;
        }

        if ($from !== null) {
            return $time >= $from;
        }

        if ($to !== null) {
            return $time <= $to;
        }

        return true;
    }

    #[Scope]
    protected function availableOn(Builder $query, CarbonInterface $moment): Builder
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
}
