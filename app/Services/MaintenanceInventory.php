<?php

namespace App\Services;

use App\Models\AirConditioner;
use App\Models\MaintenanceRecord;
use App\Models\SparePart;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class MaintenanceInventory
{
    /**
     * @param  array{air_conditioner_id: int, status: string, items: array<int, float>}|null  $previous
     */
    public function reconcile(?array $previous, MaintenanceRecord $record): void
    {
        DB::transaction(function () use ($previous, $record): void {
            $record->load('maintenanceSpareParts');

            $current = [
                'air_conditioner_id' => (int) $record->air_conditioner_id,
                'status' => $record->status,
                'items' => $record->maintenanceSpareParts
                    ->groupBy('spare_part_id')
                    ->map(fn ($items): int => $items->sum(fn ($item): int => $this->toMinorUnits($item->quantity)))
                    ->all(),
            ];

            $this->applyStockChanges($previous, $current);
            $this->refreshLastServicedDates(array_filter([
                $previous['air_conditioner_id'] ?? null,
                $current['air_conditioner_id'],
            ]));

            if ($current['status'] === 'selesai' && $record->type === 'perbaikan') {
                AirConditioner::query()
                    ->whereKey($current['air_conditioner_id'])
                    ->update(['condition' => 'baik']);
            }
        });
    }

    public function delete(MaintenanceRecord $record): void
    {
        DB::transaction(function () use ($record): void {
            $previous = $this->snapshot($record);
            $this->applyStockChanges($previous, null);
            $record->delete();
            $this->refreshLastServicedDates([$previous['air_conditioner_id']]);
        });
    }

    /**
     * @return array{air_conditioner_id: int, status: string, items: array<int, int>}
     */
    public function snapshot(MaintenanceRecord $record): array
    {
        return [
            'air_conditioner_id' => (int) $record->air_conditioner_id,
            'status' => $record->status,
            'items' => $record->maintenanceSpareParts()
                ->get(['spare_part_id', 'quantity'])
                ->groupBy('spare_part_id')
                ->map(fn ($items): int => $items->sum(fn ($item): int => $this->toMinorUnits($item->quantity)))
                ->all(),
        ];
    }

    private function applyStockChanges(?array $previous, ?array $current): void
    {
        $previousUsage = ($previous['status'] ?? null) === 'selesai' ? ($previous['items'] ?? []) : [];
        $currentUsage = ($current['status'] ?? null) === 'selesai' ? ($current['items'] ?? []) : [];
        $partIds = array_unique([...array_keys($previousUsage), ...array_keys($currentUsage)]);
        sort($partIds);

        $parts = SparePart::query()
            ->whereKey($partIds)
            ->orderBy('id')
            ->lockForUpdate()
            ->get()
            ->keyBy('id');

        foreach ($partIds as $partId) {
            $part = $parts->get($partId);

            if (! $part) {
                throw ValidationException::withMessages([
                    'data.maintenanceSpareParts' => 'Salah satu sparepart yang dipilih sudah tidak tersedia.',
                ]);
            }

            $stock = $this->toMinorUnits($part->stock);
            $change = ($currentUsage[$partId] ?? 0) - ($previousUsage[$partId] ?? 0);

            if (($stock - $change) < 0) {
                throw ValidationException::withMessages([
                    'data.maintenanceSpareParts' => "Stok {$part->name} tidak mencukupi untuk menyelesaikan servis.",
                ]);
            }

            if ($change !== 0) {
                $part->stock = number_format(($stock - $change) / 100, 2, '.', '');
                $part->save();
            }
        }
    }

    /**
     * @param  array<int, int|null>  $airConditionerIds
     */
    private function refreshLastServicedDates(array $airConditionerIds): void
    {
        foreach (array_unique(array_filter($airConditionerIds)) as $airConditionerId) {
            $lastServicedAt = MaintenanceRecord::query()
                ->where('air_conditioner_id', $airConditionerId)
                ->where('status', 'selesai')
                ->orderByDesc('performed_at')
                ->value('performed_at');

            AirConditioner::query()
                ->whereKey($airConditionerId)
                ->update(['last_serviced_at' => $lastServicedAt]);
        }
    }

    private function toMinorUnits(int|float|string $value): int
    {
        return (int) round((float) $value * 100);
    }
}
