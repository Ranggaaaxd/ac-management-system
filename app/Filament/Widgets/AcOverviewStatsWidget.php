<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\AirConditionerResource;
use App\Filament\Resources\MaintenanceRecordResource;
use App\Filament\Resources\SparePartResource;
use App\Models\AirConditioner;
use App\Models\MaintenanceRecord;
use App\Models\SparePart;
use Filament\Widgets\Concerns\InteractsWithPageFilters;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Database\Eloquent\Builder;

class AcOverviewStatsWidget extends StatsOverviewWidget
{
    use InteractsWithPageFilters;

    protected static bool $isLazy = false;

    protected static ?int $sort = 1;

    protected ?string $heading = 'Ringkasan Sistem AC';

    protected int|string|array $columnSpan = 'full';

    protected function getColumns(): int
    {
        return 4;
    }

    protected function getStats(): array
    {
        $buildingId = $this->filters['building_id'] ?? null;
        $airConditioners = AirConditioner::query()
            ->when($buildingId, fn (Builder $query): Builder => $query->whereHas(
                'room.floor',
                fn (Builder $query): Builder => $query->where('building_id', $buildingId),
            ));

        $maintenanceRecords = MaintenanceRecord::query()
            ->when($buildingId, fn (Builder $query): Builder => $query->whereHas(
                'airConditioner.room.floor',
                fn (Builder $query): Builder => $query->where('building_id', $buildingId),
            ))
            ->when(
                $this->filters['start_date'] ?? null,
                fn (Builder $query, string $date): Builder => $query->whereDate('performed_at', '>=', $date),
            )
            ->when(
                $this->filters['end_date'] ?? null,
                fn (Builder $query, string $date): Builder => $query->whereDate('performed_at', '<=', $date),
            );

        $damagedCount = (clone $airConditioners)->whereIn('condition', [
            'rusak_ringan',
            'rusak_berat',
            'perlu_servis',
        ])->count();

        $oldUnitCount = (clone $airConditioners)
            ->whereNotNull('production_year')
            ->where('production_year', '<=', now()->year - 5)
            ->count();

        $replacementCount = (clone $airConditioners)
            ->whereHas(
                'maintenanceRecords',
                fn (Builder $query): Builder => $query
                    ->where('status', 'selesai')
                    ->where('type', 'perbaikan'),
                '>=',
                3,
            )
            ->count();

        $lowStockCount = SparePart::query()
            ->whereColumn('stock', '<=', 'minimum_stock')
            ->count();

        return [
            Stat::make('Total AC', (clone $airConditioners)->count())
                ->icon('heroicon-m-cube')
                ->color('info')
                ->url(AirConditionerResource::getUrl('index')),
            Stat::make('AC Rusak', $damagedCount)
                ->description('Unit yang memerlukan tindak lanjut')
                ->icon('heroicon-m-exclamation-triangle')
                ->color('danger')
                ->url(AirConditionerResource::getUrl('index', [
                    'tableFilters' => [
                        'condition' => ['values' => ['rusak_ringan', 'rusak_berat', 'perlu_servis']],
                    ],
                ])),
            Stat::make('Total Perbaikan', (clone $maintenanceRecords)->where('type', 'perbaikan')->count())
                ->icon('heroicon-m-wrench-screwdriver')
                ->color('warning')
                ->url(MaintenanceRecordResource::getUrl('index', [
                    'tableFilters' => ['type' => ['value' => 'perbaikan']],
                ])),
            Stat::make('Total Pemasangan', (clone $maintenanceRecords)->where('type', 'pemasangan_baru')->count())
                ->icon('heroicon-m-plus-circle')
                ->color('success')
                ->url(MaintenanceRecordResource::getUrl('index', [
                    'tableFilters' => ['type' => ['value' => 'pemasangan_baru']],
                ])),
            Stat::make('Total Pengecekan', (clone $maintenanceRecords)->where('type', 'pengecekan')->count())
                ->icon('heroicon-m-clipboard-document-check')
                ->color('gray')
                ->url(MaintenanceRecordResource::getUrl('index', [
                    'tableFilters' => ['type' => ['value' => 'pengecekan']],
                ])),
            Stat::make('AC Usia Lama', $oldUnitCount)
                ->description('Usia produksi minimal 5 tahun')
                ->icon('heroicon-m-clock')
                ->color('gray')
                ->url(AirConditionerResource::getUrl('index')),
            Stat::make('Rekomendasi Ganti Unit', $replacementCount)
                ->description('Memiliki minimal 3 perbaikan selesai')
                ->icon('heroicon-m-arrow-path-rounded-square')
                ->color('danger')
                ->url(AirConditionerResource::getUrl('index')),
            Stat::make('Stok Menipis', $lowStockCount)
                ->icon('heroicon-m-archive-box')
                ->color($lowStockCount > 0 ? 'danger' : 'success')
                ->url(SparePartResource::getUrl('index')),
        ];
    }

    public function updatedFilters(): void
    {
        $this->cachedStats = null;
    }
}
