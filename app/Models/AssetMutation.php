<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AssetMutation extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'mutation_date' => 'date',
        ];
    }

    public function airConditioner(): BelongsTo
    {
        return $this->belongsTo(AirConditioner::class);
    }

    public function fromRoom(): BelongsTo
    {
        return $this->belongsTo(Room::class, 'from_room_id');
    }

    public function toRoom(): BelongsTo
    {
        return $this->belongsTo(Room::class, 'to_room_id');
    }
}
