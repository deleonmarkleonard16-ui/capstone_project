<?php

namespace App\Events;

use App\Models\ServiceRequest;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class DocumentRequestClaimed implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public int    $requestId;
    public string $reference;
    public string $module;   // 'good-moral' | 'exit-form'
    public string $orNumber;
    public string $orDate;

    /**
     * @param ServiceRequest $serviceRequest The claimed request.
     * @param string         $module         'good-moral' or 'exit-form'.
     */
    public function __construct(ServiceRequest $serviceRequest, string $module)
    {
        $this->requestId = $serviceRequest->getKey();
        $this->reference = (string) $serviceRequest->reference;
        $this->module    = $module;
        $this->orNumber  = (string) ($serviceRequest->or_number ?? '');
        $this->orDate    = $serviceRequest->or_date
            ? $serviceRequest->or_date->format('Y-m-d')
            : '';
    }

    /**
     * Broadcast on the document module channel so dashboards update instantly
     * when a student physically claims their certificate at the Guidance Office.
     */
    public function broadcastOn(): Channel
    {
        return new Channel('guidance.documents.' . $this->module);
    }

    public function broadcastAs(): string
    {
        return 'document.claimed';
    }

    public function broadcastWith(): array
    {
        return [
            'id'        => $this->requestId,
            'reference' => $this->reference,
            'module'    => $this->module,
            'or_number' => $this->orNumber,
            'or_date'   => $this->orDate,
        ];
    }
}
