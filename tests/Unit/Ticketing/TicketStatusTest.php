<?php

namespace Tests\Unit\Ticketing;

use App\Domain\Ticketing\TicketStatus;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

#[Group('t-seq')]
class TicketStatusTest extends TestCase
{
    public function test_waiting_ticket_can_be_called_cancelled_or_expired(): void
    {
        $this->assertTrue(TicketStatus::Waiting->canTransitionTo(TicketStatus::Called));
        $this->assertTrue(TicketStatus::Waiting->canTransitionTo(TicketStatus::Cancelled));
        $this->assertTrue(TicketStatus::Waiting->canTransitionTo(TicketStatus::Expired));
        $this->assertFalse(TicketStatus::Waiting->canTransitionTo(TicketStatus::InService));
        $this->assertFalse(TicketStatus::Waiting->canTransitionTo(TicketStatus::Finished));
    }

    public function test_called_ticket_can_start_or_be_marked_as_no_show(): void
    {
        $this->assertTrue(TicketStatus::Called->canTransitionTo(TicketStatus::InService));
        $this->assertTrue(TicketStatus::Called->canTransitionTo(TicketStatus::NoShow));
        $this->assertTrue(TicketStatus::Called->canTransitionTo(TicketStatus::Cancelled));
        $this->assertFalse(TicketStatus::Called->canTransitionTo(TicketStatus::Waiting));
    }

    public function test_ticket_in_service_can_only_be_finished(): void
    {
        $this->assertTrue(TicketStatus::InService->canTransitionTo(TicketStatus::Finished));
        $this->assertFalse(TicketStatus::InService->canTransitionTo(TicketStatus::Called));
        $this->assertFalse(TicketStatus::InService->canTransitionTo(TicketStatus::NoShow));
    }

    public function test_terminal_statuses_have_no_transitions(): void
    {
        foreach ([TicketStatus::Finished, TicketStatus::NoShow, TicketStatus::Cancelled, TicketStatus::Expired] as $status) {
            $this->assertTrue($status->isTerminal());
            $this->assertSame([], $status->allowedTransitions());
        }
    }

    public function test_only_called_and_in_service_are_active(): void
    {
        $active = array_values(array_filter(
            TicketStatus::cases(),
            fn (TicketStatus $status): bool => $status->isActive(),
        ));

        $this->assertSame([TicketStatus::Called, TicketStatus::InService], $active);
    }
}
