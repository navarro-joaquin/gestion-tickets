<?php

namespace App\Domain\Ticketing\Actions;

use App\Domain\Priorities\PriorityProfileResolver;
use App\Domain\Sections\Category;
use App\Domain\Sections\Section;
use App\Domain\Sections\SectionActivationGate;
use App\Domain\Sections\SectionTicketCode;
use App\Domain\Services\Service;
use App\Domain\Ticketing\QueueNotifier;
use App\Domain\Ticketing\Ticket;
use App\Domain\Ticketing\TicketEvent;
use App\Domain\Ticketing\TicketEventType;
use App\Domain\Ticketing\TicketSequence;
use App\Domain\Ticketing\TicketStatus;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class IssueTicket
{
    public function __construct(
        private readonly SectionActivationGate $gate,
        private readonly QueueNotifier $notifier,
        private readonly PriorityProfileResolver $profiles,
    ) {}

    public function handle(
        string $sectionSlug,
        ?string $categorySlug = null,
        ?string $serviceSlug = null,
        ?CarbonInterface $moment = null,
    ): Ticket {
        $now = $moment ? Carbon::instance($moment) : now();

        $ticket = DB::transaction(function () use ($sectionSlug, $categorySlug, $serviceSlug, $now): Ticket {
            $section = Section::query()
                ->where('slug', $sectionSlug)
                ->lockForUpdate()
                ->first();

            if (! $section) {
                throw ValidationException::withMessages([
                    'section' => ['La sección indicada no existe.'],
                ]);
            }

            if (! $this->gate->isOperational($section)) {
                throw ValidationException::withMessages([
                    'section' => ['La sección no está operativa: la matriz debe estar confirmada y ser válida.'],
                ]);
            }

            $profile = $this->profiles->resolve($now);

            $service = null;

            if ($serviceSlug !== null) {
                $service = Service::query()
                    ->where('section_id', $section->id)
                    ->where('slug', $serviceSlug)
                    ->lockForUpdate()
                    ->first();

                if (! $service) {
                    throw ValidationException::withMessages([
                        'service' => ['El servicio indicado no existe en esta sección.'],
                    ]);
                }

                if (! $service->isAvailableAt($now)) {
                    throw ValidationException::withMessages([
                        'service' => ['El servicio no está disponible en este momento.'],
                    ]);
                }

                $category = $service->category
                    ?? Category::query()->where('slug', 'general')->first();
            } else {
                if ($categorySlug === null) {
                    throw ValidationException::withMessages([
                        'category' => ['Debe indicar una categoría o un servicio.'],
                    ]);
                }

                $category = Category::query()->where('slug', $categorySlug)->first();
            }

            if (! $category) {
                throw ValidationException::withMessages([
                    'category' => ['La categoría indicada no existe.'],
                ]);
            }

            $ticketCode = SectionTicketCode::query()
                ->where('section_id', $section->id)
                ->where('is_active', true)
                ->whereHas('categories', fn (Builder $query): Builder => $query->whereKey($category->id))
                ->first();

            if (! $ticketCode) {
                throw ValidationException::withMessages([
                    'category' => ['La categoría seleccionada no está disponible en esta sección.'],
                ]);
            }

            $periodKey = $now->toDateString();

            TicketSequence::query()->insertOrIgnore([
                'section_id' => $section->id,
                'code' => $ticketCode->code,
                'period_key' => $periodKey,
                'last_number' => 0,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            $sequence = TicketSequence::query()
                ->where('section_id', $section->id)
                ->where('code', $ticketCode->code)
                ->where('period_key', $periodKey)
                ->lockForUpdate()
                ->firstOrFail();

            $sequence->increment('last_number');

            $ticket = Ticket::query()->create([
                'section_id' => $section->id,
                'current_section_id' => $section->id,
                'section_ticket_code_id' => $ticketCode->id,
                'service_id' => $service?->id,
                'category_id' => $category->id,
                'service_label' => $service?->label,
                'is_preferential' => $category->is_preferential,
                'priority_profile_id' => $profile->getKey(),
                'priority_score' => 0,
                'priority_snapshot' => [
                    'profile_id' => $profile->getKey(),
                    'profile_name' => $profile->name,
                    'ratios' => [
                        'general' => $profile->general_ratio,
                        'preferential' => $profile->preferential_ratio,
                    ],
                    'aging_minutes' => $profile->aging_minutes,
                    'max_consecutive_preferential' => $profile->max_consecutive_preferential,
                    'selected_group' => $category->is_preferential ? 'preferential' : 'general',
                    'reason' => 'issued',
                    'waited_minutes' => 0,
                    'score' => 0,
                ],
                'code' => $ticketCode->code,
                'period_key' => $periodKey,
                'number' => $sequence->last_number,
                'status' => TicketStatus::Waiting,
                'call_count' => 0,
                'issued_at' => $now,
            ]);

            TicketEvent::query()->create([
                'ticket_id' => $ticket->id,
                'type' => TicketEventType::Issued,
                'occurred_at' => $now,
                'payload' => [
                    'category' => $category->slug,
                    'service' => $service?->slug,
                    'code' => $ticketCode->code,
                    'number' => $ticket->number,
                ],
            ]);

            return $ticket;
        });

        $this->notifier->notify($sectionSlug);

        return $ticket->load(['section', 'currentSection', 'category', 'ticketCode', 'service']);
    }
}
