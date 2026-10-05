<?php

namespace App\Events;

use App\Models\AdmissionApplicant;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ApplicantSessionResumedEvent implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public AdmissionApplicant $applicant;

    public function __construct(AdmissionApplicant $applicant)
    {
        $this->applicant = $applicant;
    }

    public function broadcastOn(): array
    {
        return [
            new Channel('admission-applicant.' . $this->applicant->id),
            new Channel('admission-session.' . $this->applicant->admission_session_id),
        ];
    }

    public function broadcastWith(): array
    {
        return [
            'applicant_id' => $this->applicant->id,
            'strike_count' => 0,
            'resumed'      => true,
            'take_url'     => route('admission.take', $this->applicant->exam_token ?? ''),
        ];
    }
}
