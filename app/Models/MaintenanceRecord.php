<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MaintenanceRecord extends Model
{
    protected $guarded = [];

    public function getTotalCostAttribute(): float
    {
        return round(
            (float) $this->labor_cost + (float) $this->maintenanceSpareParts->sum('subtotal'),
            2,
        );
    }

    protected function casts(): array
    {
        return [
            'labor_cost' => 'decimal:2',
            'pressure_psi' => 'decimal:2',
            'current_ampere' => 'decimal:2',
            'blow_temp_celsius' => 'decimal:2',
            'voltage' => 'decimal:2',
            'performed_at' => 'date',
        ];
    }

    public function airConditioner(): BelongsTo
    {
        return $this->belongsTo(AirConditioner::class);
    }

    public function technician(): BelongsTo
    {
        return $this->belongsTo(Technician::class);
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    public function maintenanceSpareParts(): HasMany
    {
        return $this->hasMany(MaintenanceSparePart::class);
    }

    public function spareParts(): BelongsToMany
    {
        return $this->belongsToMany(SparePart::class, 'maintenance_spare_parts')
            ->using(MaintenanceSparePart::class)
            ->withPivot(['id', 'quantity', 'unit_price', 'subtotal'])
            ->withTimestamps();
    }
}
