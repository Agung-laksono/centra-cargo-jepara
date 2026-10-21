<?php

namespace App\Events;

use App\Models\DriverExpedition;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class PartnershipStatusUpdated implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $partnershipId;
    public $driverId;
    public $userId;
    public $expeditionId;
    public $expeditionName;
    public $status;
    public $statusLabel;
    public $message;
    public $partneredAt;
    public $timestamp;

    /**
     * Create a new event instance.
     */
    public function __construct(DriverExpedition $partnership, ?string $customMessage = null)
    {
        $partnership->loadMissing(['driver', 'user', 'expedition']);

        $this->partnershipId = $partnership->id;
        $this->driverId = $partnership->driver_id;
        $this->userId = $partnership->user_id;
        $this->expeditionId = $partnership->expedition_id;
        $this->expeditionName = $partnership->expedition?->name ?? 'Gudang Ekspedisi';
        $this->status = $partnership->status;

        $labels = [
            'ACTIVE' => 'Aktif (Disetujui)',
            'REJECTED' => 'Ditolak',
            'INACTIVE' => 'Dinonaktifkan',
            'PENDING' => 'Menunggu Verifikasi',
        ];
        $this->statusLabel = $labels[$partnership->status] ?? $partnership->status;

        $this->message = $customMessage ?: (
            $partnership->status === 'ACTIVE'
                ? "Selamat! Kemitraan Anda telah disetujui oleh {$this->expeditionName}."
                : "Status kemitraan Anda di {$this->expeditionName} kini: {$this->statusLabel}."
        );

        $this->partneredAt = $partnership->partnered_at ? $partnership->partnered_at->format('d/m/Y H:i') : null;
        $this->timestamp = now()->toIso8601String();
    }

    /**
     * Get the channels the event should broadcast on.
     */
    public function broadcastOn(): array
    {
        $channels = [
            new Channel('driver-partnerships'),
        ];

        if ($this->driverId) {
            $channels[] = new Channel('driver.' . $this->driverId);
        }

        if ($this->userId) {
            $channels[] = new Channel('user.' . $this->userId);
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
        return 'partnership.status_updated';
    }
}
