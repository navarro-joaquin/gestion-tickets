<?php

namespace App\Domain\Ticketing;

enum TicketEventType: string
{
    case Issued = 'issued';
    case Called = 'called';
    case Recalled = 'recalled';
    case Started = 'started';
    case Finished = 'finished';
    case NoShow = 'no_show';
    case Cancelled = 'cancelled';
    case Expired = 'expired';
}
