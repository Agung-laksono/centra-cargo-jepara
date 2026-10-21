<?php

namespace App\Events;

use App\Models\Shipment;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ShipmentStatusUpdated implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $shipmentId;
    public $trackingNumber;
    public $status;
    public $locationDesc;
    public $koliId;
    public $expeditionId;
    public $timestamp;

    /**
     * Create a new event instance.
     */
    public function __construct(int $shipmentId, string $status, string $locationDesc, ?int $koliId = null)
    {
        $this->shipmentId = $shipmentId;
        $this->status = $status;
        $this->locationDesc = $locationDesc;
        $this->koliId = $koliId;
        $this->timestamp = now()->toIso8601String();

        $shipment = Shipment::withoutGlobalScope('expedition')->find($shipmentId);
        $this->trackingNumber = $shipment?->tracking_number ?? '';
        $this->expeditionId = $shipment?->expedition_id;
    }

    /**
     * Get the channels the event should broadcast on.
     */
    public function broadcastOn(): array
    {
        $channels = [
            new Channel('shipments'),
        ];

        if ($this->trackingNumber) {
            $channels[] = new Channel('shipment.' . $this->trackingNumber);
        }

        if ($this->expeditionId) {
            $channels[] = new Channel('expedition.' . $this->expeditionId);
        }

        return $channels;
    }

    /**
     * Nama event broadcast untuk client Echo
     */
    public function broadcastAs(): string
    {
        return 'shipment.status_updated';
    }
}
