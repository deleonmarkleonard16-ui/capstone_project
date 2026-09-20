<?php

namespace App\Events;

use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;

class GuidanceBatchStarted implements ShouldBroadcast, ShouldDispatchAfterCommit
{
    use Dispatchable;
    public function __construct(public int $batchId, public string $startedAt) {}
    public function broadcastOn(): array { return [new PrivateChannel('guidance.batch.'.$this->batchId)]; }
    public function broadcastAs(): string { return 'guidance.batch.started'; }
}
