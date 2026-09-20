<?php

namespace App\Events;

use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;

class GuidanceSecurityStrikeLogged implements ShouldBroadcast, ShouldDispatchAfterCommit
{
    use Dispatchable;

    public function __construct(public int $appointmentId, public int $strikeNumber, public string $incidentType) {}

    public function broadcastOn(): array
    {
        return [new PrivateChannel('guidance.proctoring')];
    }

    public function broadcastAs(): string
    {
        return 'guidance.security.strike';
    }
}
