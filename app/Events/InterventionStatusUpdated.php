<?php

namespace App\Events;

use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;

class InterventionStatusUpdated implements ShouldBroadcast, ShouldDispatchAfterCommit
{
    use Dispatchable;

    public function __construct(
        public int $appointmentId,
        public string $status,
        public ?string $notes = null,
        public ?string $updatedAt = null
    ) {
        $this->updatedAt = $this->updatedAt ?? now()->toIso8601String();
    }

    public function broadcastOn(): array
    {
        return [new PrivateChannel('guidance.interventions')];
    }

    public function broadcastAs(): string
    {
        return 'guidance.intervention.updated';
    }
}
