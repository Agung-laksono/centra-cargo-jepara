<?php

namespace App\Events;

use App\Models\Expedition;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ExpeditionRegistered implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $expeditionId;
    public $name;
    public $domainSlug;
    public $receiptCode;
    public $themeColor;
    public $phone;
    public $timestamp;

    /**
     * Create a new event instance.
     */
    public function __construct(Expedition $expedition)
    {
        $this->expeditionId = $expedition->id;
        $this->name = $expedition->name;
        $this->domainSlug = $expedition->domain_slug;
        $this->receiptCode = $expedition->receipt_code ?: 'EXP';
        $this->themeColor = $expedition->theme_color ?: '#10b981';
        $this->phone = $expedition->phone;
        $this->timestamp = now()->toIso8601String();
    }

    /**
     * Get the channels the event should broadcast on.
     */
    public function broadcastOn(): array
    {
        return [
            new Channel('platform'),
            new Channel('expeditions'),
        ];
    }

    /**
     * Nama event broadcast untuk client Echo
     */
    public function broadcastAs(): string
    {
        return 'expedition.registered';
    }
}
