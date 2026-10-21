<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class TestBroadcastEvent implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $message;
    public $driver;
    public $timestamp;

    /**
     * Create a new event instance.
     */
    public function __construct($message = 'Koneksi WebSocket Real-time Berhasil!')
    {
        $this->message = $message;
        $this->driver = config('broadcasting.default');
        $this->timestamp = now()->format('H:i:s');
    }

    /**
     * Get the channels the event should broadcast on.
     */
    public function broadcastOn(): array
    {
        return [
            new Channel('test-channel'),
        ];
    }

    /**
     * Nama event broadcast untuk client Echo
     */
    public function broadcastAs(): string
    {
        return 'test.connection';
    }
}
