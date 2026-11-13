<?php

namespace App\Services;

use App\Models\SparePart;
use App\Models\SparePartStockIn;
use Illuminate\Support\Facades\DB;

class SparePartStockInService
{
    /**
     * @param  array{quantity: numeric-string|int|float, incoming_date: string, supplier?: ?string, purchase_price?: numeric-string|int|float|null, notes?: ?string}  $data
     */
    public function add(SparePart $sparePart, array $data): SparePartStockIn
    {
        return DB::transaction(function () use ($sparePart, $data): SparePartStockIn {
            $stockIn = $sparePart->stockIns()->create($data);
            $sparePart->increment('stock', $data['quantity']);

            app(ActivityLogger::class)->log(
                'UPDATE',
                'Sparepart',
                sprintf(
                    'Stok %s ditambah %s %s.',
                    $sparePart->name,
                    rtrim(rtrim(number_format((float) $data['quantity'], 2, ',', '.'), '0'), ','),
                    $sparePart->unit,
                ),
            );

            return $stockIn;
        });
    }
}
