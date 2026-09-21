<?php

namespace App\Events;

use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;

class AdmissionSubmissionRecorded implements ShouldBroadcast, ShouldDispatchAfterCommit
{
    use Dispatchable;

    public function __construct(public int $cycleId, public int $applicantId) {}
    public function broadcastOn(): array { return [new PrivateChannel('admission.cycle.'.$this->cycleId)]; }
    public function broadcastAs(): string { return 'admission.submission.recorded'; }
}
