<?php

namespace App\Events;

use App\Models\ServiceRequest;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ServiceRequestStatusChanged implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public string $module;
    public string $reference;
    public string $status;
    public int    $requestId;

    /**
     * @param ServiceRequest $serviceRequest The updated request.
     * @param string         $module         The guidance module key (psychological | personality | career | good-moral | exit-form).
     */
    public function __construct(ServiceRequest $serviceRequest, string $module)
    {
        $this->requestId = $serviceRequest->getKey();
        $this->reference = (string) $serviceRequest->reference;
        $this->status    = (string) $serviceRequest->status;
        $this->module    = $module;
    }

    /**
     * Broadcast on a per-module channel so the live queue JS can refresh
     * only the affected module tab without polling all modules.
     */
    public function broadcastOn(): Channel
    {
        return new Channel('guidance.requests.' . $this->module);
    }

    public function broadcastAs(): string
    {
        return 'status.changed';
    }

    public function broadcastWith(): array
    {
        return [
            'id'        => $this->requestId,
            'reference' => $this->reference,
            'status'    => $this->status,
            'module'    => $this->module,
        ];
    }
}
