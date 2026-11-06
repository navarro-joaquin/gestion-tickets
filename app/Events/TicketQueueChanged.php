<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class TicketQueueChanged implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public readonly string $sectionSlug) {}

    public function broadcastOn(): Channel
    {
        return new Channel('sections.'.$this->sectionSlug);
    }

    public function broadcastAs(): string
    {
        return 'queue.changed';
    }

    /**
     * @return array<string, string>
     */
    public function broadcastWith(): array
    {
        return [
            'section' => $this->sectionSlug,
            'at' => now()->toIso8601String(),
        ];
    }
}
