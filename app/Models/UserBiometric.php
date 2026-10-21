<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UserBiometric extends Model
{
    protected $fillable = [
        'user_id',
        'credential_id',
        'device_name',
        'auth_token',
        'last_used_at',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
