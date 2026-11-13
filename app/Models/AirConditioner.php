<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AirConditioner extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'production_year' => 'integer',
            'purchase_price' => 'decimal:2',
            'warranty_until' => 'date',
            'last_serviced_at' => 'date',
        ];
    }

    public function room(): BelongsTo
    {
        return $this->belongsTo(Room::class);
    }

    public function assetMutations(): HasMany
    {
        return $this->hasMany(AssetMutation::class);
    }

    public function maintenanceRecords(): HasMany
    {
        return $this->hasMany(MaintenanceRecord::class);
    }
}
