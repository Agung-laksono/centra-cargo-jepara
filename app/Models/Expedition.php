<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Expedition extends Model
{
    protected $fillable = [
        'name',
        'domain_slug',
        'receipt_code',
        'tagline',
        'address',
        'phone',
        'theme_color',
        'logo_url',
        'is_active',
    ];

    public function users()
    {
        return $this->hasMany(User::class);
    }

    public function warehouses()
    {
        return $this->hasMany(Warehouse::class);
    }

    public function shipments()
    {
        return $this->hasMany(Shipment::class);
    }

    public function manifests()
    {
        return $this->hasMany(Manifest::class);
    }

    public function drivers()
    {
        return $this->hasMany(Driver::class);
    }
}
