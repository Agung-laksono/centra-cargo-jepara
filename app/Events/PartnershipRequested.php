<?php

namespace App\Events;

use App\Models\DriverExpedition;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class PartnershipRequested implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $partnershipId;
    public $driverId;
    public $userId;
    public $driverName;
    public $phone;
    public $vehicleName;
    public $role;
    public $roleLabel;
    public $expeditionId;
    public $expeditionName;
    public $status;
    public $createdAt;
    public $timestamp;

    /**
     * Create a new event instance.
     */
    public function __construct(DriverExpedition $partnership)
    {
        $partnership->loadMissing(['driver', 'user', 'expedition']);

        $this->partnershipId = $partnership->id;
        $this->driverId = $partnership->driver_id;
        $this->userId = $partnership->user_id;
        $this->driverName = $partnership->driver?->name ?? $partnership->user?->name ?? 'Supir Armada';
        $this->phone = $partnership->driver?->phone_number ?? $partnership->user?->phone ?? '-';
        $this->vehicleName = $partnership->driver?->vehicle_name ?? 'Armada Standar';
        $this->role = $partnership->role;
        $this->roleLabel = $partnership->role === 'DRIVER_PICKUP' ? 'Supir Penjemputan (Lokal)' : 'Supir Pengantaran (Lintas Kota)';
        $this->expeditionId = $partnership->expedition_id;
        $this->expeditionName = $partnership->expedition?->name ?? 'Gudang Ekspedisi';
        $this->status = $partnership->status;
        $this->createdAt = $partnership->created_at ? $partnership->created_at->format('d/m/Y H:i') : now()->format('d/m/Y H:i');
        $this->timestamp = now()->toIso8601String();
    }

    /**
     * Get the channels the event should broadcast on.
     */
    public function broadcastOn(): array
    {
        $channels = [
            new Channel('expeditions'),
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
        return 'partnership.requested';
    }
}
