<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Expedition extends Model
{
    protected $fillable = [
        'name',
        'domain_slug',
        'partnership_token',
        'receipt_code',
        'tagline',
        'address',
        'phone',
        'theme_color',
        'logo_url',
        'is_active',
    ];

    public function partnerDrivers()
    {
        return $this->belongsToMany(Driver::class, 'driver_expeditions')
            ->withPivot(['id', 'status', 'role', 'partnered_at'])
            ->withTimestamps();
    }

    public function driverPartnerships()
    {
        return $this->hasMany(DriverExpedition::class);
    }

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
