<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Shipment extends Model
{
    use \App\Traits\BelongsToExpedition;
    protected $guarded = [];

    protected $casts = [
        'destination_province' => 'array',
        'destination_city' => 'array',
        'destination_district' => 'array',
    ];

    public function sender()
    {
        return $this->belongsTo(Sender::class);
    }

    public function items()
    {
        return $this->hasMany(ShipmentItem::class);
    }

    public function kolis()
    {
        return $this->hasMany(Koli::class);
    }

    public function currentWarehouse()
    {
        return $this->belongsTo(Warehouse::class, 'current_warehouse_id');
    }

    public function trackingLogs()
    {
        return $this->hasMany(TrackingLog::class)->orderBy('created_at', 'desc');
    }
}
