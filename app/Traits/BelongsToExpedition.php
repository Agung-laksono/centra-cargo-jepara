<?php

namespace App\Traits;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

trait BelongsToExpedition
{
    /**
     * Boot the trait to add the global scope.
     */
    protected static function bootBelongsToExpedition()
    {
        static::addGlobalScope('expedition', function (Builder $builder) {
            // Hindari looping rekursif jika builder saat ini adalah model User
            if ($builder->getModel() instanceof \App\Models\User) {
                return;
            }

            if (auth()->check()) {
                // If the user has an expedition_id (meaning they are an Expedition Admin or Staff),
                // filter the queries to only show data for their expedition.
                // If they don't have an expedition_id, it means they are Super Admin, so they can see all data.
                $user = auth()->user();
                if ($user && $user->expedition_id !== null) {
                    $builder->where($builder->getModel()->getTable() . '.expedition_id', $user->expedition_id);
                }
            }
        });

        static::creating(function (Model $model) {
            if (auth()->check() && auth()->user()->expedition_id !== null) {
                // Automatically set expedition_id on create
                if (empty($model->expedition_id)) {
                    $model->expedition_id = auth()->user()->expedition_id;
                }
            }
        });
    }

    /**
     * Define the relationship to the Expedition model.
     */
    public function expedition()
    {
        return $this->belongsTo(\App\Models\Expedition::class);
    }
}
