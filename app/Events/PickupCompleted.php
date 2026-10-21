<?php

namespace App\Events;

use App\Models\Manifest;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class PickupCompleted implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $manifestId;
    public $manifestNumber;
    public $driverName;
    public $senderId;
    public $senderName;
    public $koliCount;
    public $isForce;
    public $expeditionId;
    public $timestamp;

    /**
     * Create a new event instance.
     */
    public function __construct(Manifest $manifest, int $senderId, int $koliCount, bool $isForce = false, ?string $senderName = null)
    {
        $manifest->loadMissing(['driver']);

        $this->manifestId = $manifest->id;
        $this->manifestNumber = $manifest->manifest_number;
        $this->driverName = $manifest->driver?->name ?? 'Armada Penjemputan';
        $this->senderId = $senderId;
        $this->senderName = $senderName;
        $this->koliCount = $koliCount;
        $this->isForce = $isForce;
        $this->expeditionId = $manifest->expedition_id;
        $this->timestamp = now()->toIso8601String();
    }

    /**
     * Get the channels the event should broadcast on.
     */
    public function broadcastOn(): array
    {
        $channels = [
            new Channel('manifests'),
            new Channel('manifest.' . $this->manifestId),
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
        return 'pickup.completed';
    }
}
