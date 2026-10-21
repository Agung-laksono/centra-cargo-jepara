<?php

namespace App\Events;

use App\Models\Shipment;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ShipmentDelivered implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $shipmentId;
    public $trackingNumber;
    public $recipientName;
    public $paymentType;
    public $totalCod;
    public $expeditionId;
    public $timestamp;

    /**
     * Create a new event instance.
     */
    public function __construct(Shipment $shipment)
    {
        $this->shipmentId = $shipment->id;
        $this->trackingNumber = $shipment->tracking_number;
        $this->recipientName = $shipment->recipient_name;
        $this->paymentType = $shipment->payment_type;
        $this->totalCod = (float) $shipment->total_cod;
        $this->expeditionId = $shipment->expedition_id;
        $this->timestamp = now()->toIso8601String();
    }

    /**
     * Get the channels the event should broadcast on.
     */
    public function broadcastOn(): array
    {
        $channels = [
            new Channel('shipments'),
            new Channel('manifests'),
        ];

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
        return 'shipment.delivered';
    }
}
