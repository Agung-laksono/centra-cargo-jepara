<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DriverExpedition extends Model
{
    protected $fillable = [
        'driver_id',
        'user_id',
        'expedition_id',
        'role',
        'status',
        'handshake_code',
        'partnered_at',
    ];

    protected function casts(): array
    {
        return [
            'partnered_at' => 'datetime',
        ];
    }

    public function driver()
    {
        return $this->belongsTo(Driver::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function expedition()
    {
        return $this->belongsTo(Expedition::class);
    }
}
