<?php

namespace App\Domain\Ticketing;

use App\Events\TicketQueueChanged;
use Throwable;

use function Illuminate\Support\defer;

final class QueueNotifier
{
    /**
     * Avisa por WebSocket que la cola de una sección cambió.
     *
     * El envío se difiere hasta después de responder la petición (o de terminar el
     * comando), para que un Reverb lento no retrase la emisión ni la llamada de
     * tickets. Varios avisos de la misma sección en una petición se agrupan en uno.
     */
    public function notify(string $sectionSlug): void
    {
        defer(function () use ($sectionSlug): void {
            try {
                TicketQueueChanged::dispatch($sectionSlug);
            } catch (Throwable $exception) {
                report($exception);
            }
        }, "ticket-queue-changed:{$sectionSlug}")->always();
    }
}
