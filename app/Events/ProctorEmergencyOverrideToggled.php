<?php

namespace App\Events;

use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;

class ProctorEmergencyOverrideToggled implements ShouldBroadcast, ShouldDispatchAfterCommit
{
    use Dispatchable;

    public function __construct(
        public bool $isPaused,
        public string $toggledBy,
        public string $timestamp
    ) {}

    public function broadcastOn(): array
    {
        return [new PrivateChannel('guidance.proctoring')];
    }

    public function broadcastAs(): string
    {
        return 'guidance.proctor.override';
    }
}
