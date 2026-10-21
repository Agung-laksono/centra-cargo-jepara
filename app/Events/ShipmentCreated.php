<?php

namespace App\Events;

use App\Models\Shipment;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ShipmentCreated implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $shipmentId;
    public $trackingNumber;
    public $senderName;
    public $recipientName;
    public $destinationCity;
    public $paymentType;
    public $freightFee;
    public $totalKoli;
    public $expeditionId;
    public $timestamp;

    /**
     * Create a new event instance.
     */
    public function __construct(Shipment $shipment)
    {
        $shipment->loadMissing(['sender', 'kolis']);

        $this->shipmentId = $shipment->id;
        $this->trackingNumber = $shipment->tracking_number;
        $this->senderName = $shipment->sender?->name ?? 'Pengirim';
        $this->recipientName = $shipment->recipient_name;
        
        $destCity = $shipment->destination_city;
        $this->destinationCity = is_array($destCity) ? ($destCity['name'] ?? ($destCity['label'] ?? '-')) : (string) $destCity;

        $this->paymentType = $shipment->payment_type;
        $this->freightFee = (float) $shipment->freight_fee;
        $this->totalKoli = $shipment->kolis->count() ?: 1;
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
        return 'shipment.created';
    }
}
