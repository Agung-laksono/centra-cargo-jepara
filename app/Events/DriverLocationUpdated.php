<?php

namespace App\Events;

use App\Models\Driver;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class DriverLocationUpdated implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $driverId;
    public $driverName;
    public $vehicleName;
    public $lat;
    public $lng;
    public $timestamp;

    /**
     * Create a new event instance.
     */
    public function __construct(Driver $driver, $lat, $lng)
    {
        $this->driverId = $driver->id;
        $this->driverName = $driver->name;
        $this->vehicleName = $driver->vehicle_name;
        $this->lat = (float) $lat;
        $this->lng = (float) $lng;
        $this->timestamp = now()->toIso8601String();
    }

    /**
     * Get the channels the event should broadcast on.
     */
    public function broadcastOn(): array
    {
        return [
            new Channel('driver-locations'),
            new Channel('driver.' . $this->driverId),
        ];
    }

    /**
     * Nama event broadcast untuk client Echo
     */
    public function broadcastAs(): string
    {
        return 'driver.location.updated';
    }
}
