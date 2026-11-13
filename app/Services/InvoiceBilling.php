<?php

namespace App\Services;

use App\Models\Invoice;
use App\Models\MaintenanceRecord;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class InvoiceBilling
{
    /**
     * @return Collection<int, MaintenanceRecord>
     */
    public function availableMaintenanceRecords(?int $buildingId = null, ?int $invoiceId = null): Collection
    {
        return MaintenanceRecord::query()
            ->with(['airConditioner.room.floor.building', 'maintenanceSpareParts'])
            ->where('status', 'selesai')
            ->where(function (Builder $query) use ($invoiceId): void {
                $query->whereNull('invoice_id');

                if ($invoiceId !== null) {
                    $query->orWhere('invoice_id', $invoiceId);
                }
            })
            ->when($buildingId !== null, fn (Builder $query): Builder => $query->whereHas(
                'airConditioner.room.floor',
                fn (Builder $query): Builder => $query->where('building_id', $buildingId),
            ))
            ->orderByDesc('performed_at')
            ->get();
    }

    /**
     * @param  array<int|string, int|string>  $recordIds
     */
    public function totalFor(array $recordIds): float
    {
        $ids = $this->normalizeIds($recordIds);

        if ($ids === []) {
            return 0;
        }

        $labor = MaintenanceRecord::query()
            ->whereIn('id', $ids)
            ->where('status', 'selesai')
            ->sum('labor_cost');

        $parts = DB::table('maintenance_spare_parts')
            ->join('maintenance_records', 'maintenance_records.id', '=', 'maintenance_spare_parts.maintenance_record_id')
            ->whereIn('maintenance_records.id', $ids)
            ->where('maintenance_records.status', 'selesai')
            ->sum('maintenance_spare_parts.subtotal');

        return round((float) $labor + (float) $parts, 2);
    }

    /**
     * @param  array<int|string, int|string>  $recordIds
     */
    public function sync(Invoice $invoice, array $recordIds): void
    {
        DB::transaction(function () use ($invoice, $recordIds): void {
            $ids = $this->normalizeIds($recordIds);
            $records = MaintenanceRecord::query()
                ->whereKey($ids)
                ->where('status', 'selesai')
                ->where(function (Builder $query) use ($invoice): void {
                    $query->whereNull('invoice_id')
                        ->orWhere('invoice_id', $invoice->id);
                })
                ->when($invoice->building_id !== null, fn (Builder $query): Builder => $query->whereHas(
                    'airConditioner.room.floor',
                    fn (Builder $query): Builder => $query->where('building_id', $invoice->building_id),
                ))
                ->orderBy('id')
                ->lockForUpdate()
                ->get(['id']);

            if ($records->count() !== count($ids)) {
                throw ValidationException::withMessages([
                    'data.maintenance_record_ids' => 'Pilih hanya pekerjaan selesai yang belum ditagihkan atau sudah tercantum pada invoice ini, dan sesuai gedung.',
                ]);
            }

            $invoice->maintenanceRecords()
                ->when($ids !== [], fn ($query) => $query->whereNotIn('id', $ids))
                ->update(['invoice_id' => null]);

            if ($ids !== []) {
                MaintenanceRecord::query()
                    ->whereKey($ids)
                    ->update(['invoice_id' => $invoice->id]);
            }

            $invoice->update([
                'total_amount' => $this->totalFor($ids),
            ]);
        });
    }

    public function refresh(int $invoiceId): void
    {
        $invoice = Invoice::query()->find($invoiceId);

        if ($invoice === null) {
            return;
        }

        $invoice->update([
            'total_amount' => $this->totalFor($invoice->maintenanceRecords()->pluck('id')->all()),
        ]);
    }

    /**
     * @param  array<int|string, int|string>  $ids
     * @return array<int>
     */
    private function normalizeIds(array $ids): array
    {
        $normalized = [];

        foreach ($ids as $id) {
            if (! is_numeric($id) || (int) $id < 1) {
                throw ValidationException::withMessages([
                    'data.maintenance_record_ids' => 'Pilihan pekerjaan tidak valid.',
                ]);
            }

            $normalized[] = (int) $id;
        }

        return array_values(array_unique($normalized));
    }
}
