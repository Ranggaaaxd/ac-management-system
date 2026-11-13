<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SparePart extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'stock' => 'decimal:2',
            'minimum_stock' => 'decimal:2',
        ];
    }

    public function maintenanceSpareParts(): HasMany
    {
        return $this->hasMany(MaintenanceSparePart::class);
    }

    public function maintenanceRecords(): BelongsToMany
    {
        return $this->belongsToMany(MaintenanceRecord::class, 'maintenance_spare_parts')
            ->using(MaintenanceSparePart::class)
            ->withPivot(['id', 'quantity', 'unit_price', 'subtotal'])
            ->withTimestamps();
    }
}
