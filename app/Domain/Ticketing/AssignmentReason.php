<?php

namespace App\Domain\Ticketing;

enum AssignmentReason: string
{
    case Called = 'called';
    case Recalled = 'recalled';
}
