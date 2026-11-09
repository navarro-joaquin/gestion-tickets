<?php

namespace App\Policies;

use App\Domain\Operations\OperatorSession;
use App\Domain\Ticketing\Ticket;
use App\Domain\Ticketing\TicketStatus;
use App\Domain\Users\UserRole;
use App\Models\User;

class TicketPolicy
{
    public function operate(User $user, Ticket $ticket): bool
    {
        $session = $this->activeSession($user);

        return $session !== null
            && $session->section_id === $ticket->current_section_id
            && $ticket->operator_id === $user->id
            && in_array($ticket->status, [TicketStatus::Called, TicketStatus::InService], true);
    }

    public function transfer(User $user, Ticket $ticket): bool
    {
        $session = $this->activeSession($user);

        return $session !== null
            && $session->section_id === $ticket->current_section_id
            && in_array($ticket->status, [TicketStatus::Waiting, TicketStatus::Called], true);
    }

    private function activeSession(User $user): ?OperatorSession
    {
        if (! in_array($user->role, [UserRole::Operator, UserRole::Supervisor, UserRole::Admin], true)) {
            return null;
        }

        return $user->operatorSessions()
            ->active()
            ->first();
    }
}
