<?php

namespace Tests\Feature\Priorities;

use App\Domain\Priorities\PriorityProfile;
use App\Domain\Priorities\QueueSimulator;
use App\Domain\Ticketing\Actions\CallNextTicket;
use App\Domain\Ticketing\Actions\FinishTicket;
use App\Domain\Ticketing\Actions\IssueTicket;
use App\Domain\Ticketing\Actions\StartTicket;
use App\Domain\Ticketing\Ticket;
use Carbon\Carbon;
use PHPUnit\Framework\Attributes\Group;
use Tests\Concerns\SeedsTicketMatrix;
use Tests\TestCase;

#[Group('t-prio')]
class QueuePriorityTest extends TestCase
{
    use SeedsTicketMatrix;

    public function test_standard_profile_calls_two_general_tickets_per_preferential(): void
    {
        $this->seedMatrix();
        $moment = Carbon::parse('2026-11-09 08:00:00');
        $issue = app(IssueTicket::class);

        foreach (['general', 'general', 'general', 'general', 'pregnancy', 'pregnancy', 'pregnancy'] as $category) {
            $issue->handle('plataforma', $category, null, $moment);
        }

        $called = $this->callSeveral(6, $moment);

        $this->assertSame(
            [false, false, true, false, false, true],
            array_map(fn (Ticket $ticket): bool => $ticket->is_preferential, $called),
        );
    }

    public function test_the_priority_snapshot_is_persisted_when_the_ticket_is_called(): void
    {
        $this->seedMatrix();
        $moment = Carbon::parse('2026-11-09 08:00:00');
        $issue = app(IssueTicket::class);

        $general = $issue->handle('plataforma', 'general', null, $moment);
        $issue->handle('plataforma', 'pregnancy', null, $moment);

        $called = app(CallNextTicket::class)->handle('plataforma', 'Ventanilla 1', null, $moment);

        $this->assertNotNull($called);
        $this->assertSame($general->id, $called->id);
        $this->assertNotNull($called->priority_profile_id);
        $this->assertSame('Estándar 2:1', $called->priority_snapshot['profile_name']);
        $this->assertSame(['general' => 2, 'preferential' => 1], $called->priority_snapshot['ratios']);
        $this->assertSame('general', $called->priority_snapshot['selected_group']);
        $this->assertSame('ratio', $called->priority_snapshot['reason']);
        $this->assertSame(0, $called->priority_snapshot['waited_minutes']);
        $this->assertSame(0, $called->priority_score);
    }

    public function test_the_maximum_of_consecutive_preferential_calls_is_enforced(): void
    {
        $this->seedMatrix();
        $this->useProfile([
            'general_ratio' => 1,
            'preferential_ratio' => 3,
            'max_consecutive_preferential' => 1,
            'aging_minutes' => 1440,
        ]);

        $moment = Carbon::parse('2026-11-09 08:00:00');
        $issue = app(IssueTicket::class);

        foreach (['general', 'general', 'general', 'general', 'general', 'pregnancy', 'pregnancy', 'pregnancy', 'pregnancy', 'pregnancy'] as $category) {
            $issue->handle('plataforma', $category, null, $moment);
        }

        $called = $this->callSeveral(8, $moment);
        $groups = array_map(fn (Ticket $ticket): bool => $ticket->is_preferential, $called);

        for ($index = 1; $index < count($groups); $index++) {
            $this->assertFalse(
                $groups[$index] && $groups[$index - 1],
                'No deben existir dos llamados preferenciales consecutivos.',
            );
        }

        $reasons = array_map(
            fn (Ticket $ticket): string => $ticket->priority_snapshot['reason'] ?? '',
            $called,
        );

        $this->assertContains('max_consecutive_preferential', $reasons);
    }

    public function test_aging_promotes_the_oldest_preferential_ticket(): void
    {
        $this->seedMatrix();
        $this->useProfile([
            'general_ratio' => 10,
            'preferential_ratio' => 1,
            'max_consecutive_preferential' => 10,
            'aging_minutes' => 30,
        ]);

        $start = Carbon::parse('2026-11-09 08:00:00');
        $issue = app(IssueTicket::class);

        $preferential = $issue->handle('plataforma', 'pregnancy', null, $start);
        $issue->handle('plataforma', 'general', null, $start->copy()->addMinutes(5));

        $called = app(CallNextTicket::class)->handle(
            'plataforma',
            'Ventanilla 1',
            null,
            $start->copy()->addMinutes(40),
        );

        $this->assertNotNull($called);
        $this->assertSame($preferential->id, $called->id);
        $this->assertTrue($called->is_preferential);
        $this->assertSame('aging_preferential', $called->priority_snapshot['reason']);
    }

    public function test_aging_promotes_the_oldest_general_ticket(): void
    {
        $this->seedMatrix();
        $this->useProfile([
            'general_ratio' => 1,
            'preferential_ratio' => 2,
            'max_consecutive_preferential' => 10,
            'aging_minutes' => 30,
        ]);

        $start = Carbon::parse('2026-11-09 08:00:00');
        $issue = app(IssueTicket::class);

        $issue->handle('plataforma', 'general', null, $start);
        $first = app(CallNextTicket::class)->handle('plataforma', 'Ventanilla 1', null, $start);
        $this->assertNotNull($first);
        $this->finish($first, $start);

        $waitingGeneral = $issue->handle('plataforma', 'general', null, $start);
        $issue->handle('plataforma', 'pregnancy', null, $start->copy()->addMinutes(5));

        $called = app(CallNextTicket::class)->handle(
            'plataforma',
            'Ventanilla 1',
            null,
            $start->copy()->addMinutes(40),
        );

        $this->assertNotNull($called);
        $this->assertSame($waitingGeneral->id, $called->id);
        $this->assertFalse($called->is_preferential);
        $this->assertSame('aging_general', $called->priority_snapshot['reason']);
    }

    public function test_the_fallback_profile_is_used_when_no_profile_is_persisted(): void
    {
        $this->seedMatrix();
        PriorityProfile::query()->update(['is_active' => false]);

        $moment = Carbon::parse('2026-11-09 08:00:00');
        app(IssueTicket::class)->handle('plataforma', 'general', null, $moment);

        $called = app(CallNextTicket::class)->handle('plataforma', 'Ventanilla 1', null, $moment);

        $this->assertNotNull($called);
        $this->assertNull($called->priority_profile_id);
        $this->assertSame('Estándar 2:1', $called->priority_snapshot['profile_name']);
        $this->assertSame(['general' => 2, 'preferential' => 1], $called->priority_snapshot['ratios']);
    }

    public function test_the_simulator_produces_the_two_to_one_sequence(): void
    {
        $profile = new PriorityProfile([
            'name' => 'Simulación 2:1',
            'general_ratio' => 2,
            'preferential_ratio' => 1,
            'aging_minutes' => 30,
            'max_consecutive_preferential' => 1,
            'is_active' => true,
        ]);

        $sequence = app(QueueSimulator::class)->simulate(4, 2, $profile);

        $this->assertSame(
            ['general', 'general', 'preferential', 'general', 'general', 'preferential'],
            array_column($sequence, 'group'),
        );
        $this->assertSame(range(1, 6), array_column($sequence, 'position'));
    }

    /**
     * @param  array<string, int>  $attributes
     */
    private function useProfile(array $attributes): void
    {
        PriorityProfile::query()->update(['is_active' => false]);

        PriorityProfile::factory()->create(array_merge([
            'name' => 'Perfil de prueba',
            'is_active' => true,
            'effective_from' => null,
            'effective_to' => null,
        ], $attributes));
    }

    /**
     * @return list<Ticket>
     */
    private function callSeveral(int $count, Carbon $moment): array
    {
        $called = [];

        for ($index = 0; $index < $count; $index++) {
            $ticket = app(CallNextTicket::class)->handle(
                'plataforma',
                'Ventanilla 1',
                null,
                $moment,
            );

            $this->assertNotNull($ticket);

            $called[] = $ticket;
            $this->finish($ticket, $moment);
        }

        return $called;
    }

    private function finish(Ticket $ticket, Carbon $moment): void
    {
        $started = app(StartTicket::class)->handle($ticket, $moment);

        app(FinishTicket::class)->handle($started, $moment);
    }
}
