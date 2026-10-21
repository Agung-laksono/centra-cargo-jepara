<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Koli extends Model
{
    protected $guarded = [];

    protected $appends = ['barcode'];

    // Status Fisik Koli
    const STATUS_MENUNGGU_PICKUP = 'MENUNGGU_PICKUP';

    const STATUS_MENUNGGU_DROPOFF = 'MENUNGGU_DROPOFF';

    const STATUS_DI_ANGKUT = 'DI_ANGKUT';

    const STATUS_DI_GUDANG = 'DI_GUDANG';

    const STATUS_DALAM_PERJALANAN = 'DALAM_PERJALANAN';

    const STATUS_TERKIRIM = 'TERKIRIM';

    /**
     * Alias barcode attribute to barcode_id for full consistency across frontend & backend
     */
    public function getBarcodeAttribute(): ?string
    {
        return $this->attributes['barcode_id'] ?? null;
    }

    public function shipment()
    {
        return $this->belongsTo(Shipment::class);
    }
}
