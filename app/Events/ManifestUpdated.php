<?php

namespace App\Events;

use App\Models\Manifest;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ManifestUpdated implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $manifestId;
    public $actionType;
    public $message;
    public $expeditionId;
    public $timestamp;

    /**
     * Create a new event instance.
     */
    public function __construct(int $manifestId, string $actionType, string $message, ?int $expeditionId = null)
    {
        $this->manifestId = $manifestId;
        $this->actionType = $actionType;
        $this->message = $message;
        $this->timestamp = now()->toIso8601String();

        if ($expeditionId) {
            $this->expeditionId = $expeditionId;
        } else {
            $manifest = Manifest::withoutGlobalScope('expedition')->find($manifestId);
            $this->expeditionId = $manifest?->expedition_id;
        }
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
        return 'manifest.updated';
    }
}
