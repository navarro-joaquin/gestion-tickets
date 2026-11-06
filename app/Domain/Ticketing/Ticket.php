<?php

namespace App\Domain\Ticketing;

use App\Domain\Priorities\PriorityProfile;
use App\Domain\Sections\Category;
use App\Domain\Sections\Section;
use App\Domain\Sections\SectionTicketCode;
use App\Domain\Services\Service;
use App\Models\User;
use Database\Factories\Domain\Ticketing\TicketFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'section_id',
    'current_section_id',
    'section_ticket_code_id',
    'service_id',
    'category_id',
    'service_label',
    'is_preferential',
    'priority_profile_id',
    'priority_score',
    'priority_snapshot',
    'code',
    'period_key',
    'number',
    'status',
    'station_label',
    'operator_id',
    'issued_at',
    'called_at',
    'call_count',
    'started_at',
    'finished_at',
    'closed_at',
])]
class Ticket extends Model
{
    /** @use HasFactory<TicketFactory> */
    use HasFactory;

    protected static function booted(): void
    {
        static::creating(function (Ticket $ticket): void {
            $ticket->current_section_id ??= $ticket->section_id;
        });
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'period_key' => 'date',
            'number' => 'integer',
            'status' => TicketStatus::class,
            'is_preferential' => 'boolean',
            'priority_score' => 'integer',
            'priority_snapshot' => 'array',
            'call_count' => 'integer',
            'issued_at' => 'datetime',
            'called_at' => 'datetime',
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
            'closed_at' => 'datetime',
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
     * @return BelongsTo<Section, $this>
     */
    public function currentSection(): BelongsTo
    {
        return $this->belongsTo(Section::class, 'current_section_id');
    }

    /**
     * @return BelongsTo<SectionTicketCode, $this>
     */
    public function ticketCode(): BelongsTo
    {
        return $this->belongsTo(SectionTicketCode::class, 'section_ticket_code_id');
    }

    /**
     * @return BelongsTo<Category, $this>
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    /**
     * @return BelongsTo<Service, $this>
     */
    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }

    /**
     * @return BelongsTo<PriorityProfile, $this>
     */
    public function priorityProfile(): BelongsTo
    {
        return $this->belongsTo(PriorityProfile::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function operator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'operator_id');
    }

    /**
     * @return HasMany<TicketAssignment, $this>
     */
    public function assignments(): HasMany
    {
        return $this->hasMany(TicketAssignment::class);
    }

    /**
     * @return HasMany<TicketEvent, $this>
     */
    public function events(): HasMany
    {
        return $this->hasMany(TicketEvent::class)->orderBy('occurred_at');
    }

    /**
     * @return HasMany<TicketTransfer, $this>
     */
    public function transfers(): HasMany
    {
        return $this->hasMany(TicketTransfer::class)->orderBy('transferred_at');
    }

    #[Scope]
    protected function waiting(Builder $query): Builder
    {
        return $query->where('status', TicketStatus::Waiting);
    }

    #[Scope]
    protected function active(Builder $query): Builder
    {
        return $query->whereIn('status', [TicketStatus::Called, TicketStatus::InService]);
    }

    #[Scope]
    protected function forSection(Builder $query, Section|int $section): Builder
    {
        return $query->where(
            'section_id',
            $section instanceof Section ? $section->id : $section,
        );
    }

    #[Scope]
    protected function queuedInSection(Builder $query, Section|int $section): Builder
    {
        return $query->where(
            'current_section_id',
            $section instanceof Section ? $section->id : $section,
        );
    }

    protected function displayNumber(): Attribute
    {
        return Attribute::get(
            fn (): string => str_pad((string) $this->number, 3, '0', STR_PAD_LEFT),
        );
    }

    protected function isTransferred(): Attribute
    {
        return Attribute::get(
            fn (): bool => ($this->current_section_id ?? $this->section_id) !== $this->section_id,
        );
    }

    protected function label(): Attribute
    {
        return Attribute::get(
            fn (): string => $this->code.'-'.$this->display_number
                .($this->service_label ? ' · '.$this->service_label : ''),
        );
    }
}
