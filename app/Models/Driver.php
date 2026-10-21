<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Driver extends Model
{
    use \App\Traits\BelongsToExpedition;
    protected $fillable = ['name', 'vehicle_name', 'phone_number', 'avatar_path', 'expedition_id'];
}
