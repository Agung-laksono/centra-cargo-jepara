<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ShipmentItem extends Model
{
    protected $fillable = ['shipment_id', 'name', 'unit', 'qty', 'koli_per_item'];
}
