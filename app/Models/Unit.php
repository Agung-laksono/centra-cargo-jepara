<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Unit extends Model
{
    use \App\Traits\BelongsToExpedition;
    protected $fillable = ['name', 'expedition_id'];
}
