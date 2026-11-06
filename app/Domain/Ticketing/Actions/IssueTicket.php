<?php

namespace App\Domain\Ticketing\Actions;

use App\Domain\Sections\Category;
use App\Domain\Sections\Section;
use App\Domain\Sections\SectionActivationGate;
use App\Domain\Sections\SectionTicketCode;
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
    ) {}

    public function handle(
        string $sectionSlug,
        string $categorySlug,
        ?CarbonInterface $moment = null,
    ): Ticket {
        $now = $moment ? Carbon::instance($moment) : now();

        $ticket = DB::transaction(function () use ($sectionSlug, $categorySlug, $now): Ticket {
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

            $category = Category::query()->where('slug', $categorySlug)->first();

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
                'category_id' => $category->id,
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
                    'code' => $ticketCode->code,
                    'number' => $ticket->number,
                ],
            ]);

            return $ticket;
        });

        $this->notifier->notify($sectionSlug);

        return $ticket->load(['section', 'currentSection', 'category', 'ticketCode']);
    }
}
