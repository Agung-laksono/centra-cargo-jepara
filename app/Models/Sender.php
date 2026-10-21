<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Sender extends Model
{
    use \App\Traits\BelongsToExpedition;
    protected $guarded = [];
}
