<?php

namespace App\Events;

use App\Models\Manifest;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ManifestHandshaked implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $manifestId;
    public $manifestType;
    public $driverId;
    public $driverName;
    public $vehicleName;
    public $expeditionId;
    public $expeditionName;
    public $status;
    public $timestamp;

    /**
     * Create a new event instance.
     */
    public function __construct(Manifest $manifest)
    {
        $this->manifestId = $manifest->id;
        $this->manifestType = $manifest->manifest_type;
        $this->driverId = $manifest->driver_id;
        $this->driverName = $manifest->driver?->name ?? 'Supir Armada';
        $this->vehicleName = $manifest->driver?->vehicle_name ?? '-';
        $this->expeditionId = $manifest->expedition_id;
        $this->expeditionName = $manifest->expedition?->name ?? 'Gudang Ekspedisi';
        $this->status = $manifest->status;
        $this->timestamp = now()->toIso8601String();
    }

    /**
     * Get the channels the event should broadcast on.
     */
    public function broadcastOn(): array
    {
        return [
            new Channel('manifests'),
            new Channel('manifest.' . $this->manifestId),
        ];
    }

    /**
     * Nama event broadcast untuk client Echo
     */
    public function broadcastAs(): string
    {
        return 'manifest.handshaked';
    }
}
