<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Room extends Model
{
    protected $guarded = [];

    public function floor(): BelongsTo
    {
        return $this->belongsTo(Floor::class);
    }

    public function airConditioners(): HasMany
    {
        return $this->hasMany(AirConditioner::class);
    }

    public function assetMutationsFrom(): HasMany
    {
        return $this->hasMany(AssetMutation::class, 'from_room_id');
    }

    public function assetMutationsTo(): HasMany
    {
        return $this->hasMany(AssetMutation::class, 'to_room_id');
    }
}
