<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Driver extends Model
{
    use \App\Traits\BelongsToExpedition;
    protected $fillable = ['name', 'vehicle_name', 'phone_number', 'avatar_path', 'expedition_id', 'user_id'];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function partnerships()
    {
        return $this->hasMany(DriverExpedition::class);
    }

    public function partnerExpeditions()
    {
        return $this->belongsToMany(Expedition::class, 'driver_expeditions')
            ->withPivot(['status', 'role', 'partnered_at'])
            ->withTimestamps();
    }
}
