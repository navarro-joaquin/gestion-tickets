<?php

namespace App\Domain\Ticketing;

use App\Events\TicketQueueChanged;
use Throwable;

final class QueueNotifier
{
    public function notify(string $sectionSlug): void
    {
        try {
            TicketQueueChanged::dispatch($sectionSlug);
        } catch (Throwable $exception) {
            report($exception);
        }
    }
}
