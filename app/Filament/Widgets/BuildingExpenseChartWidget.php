<?php

namespace App\Filament\Widgets;

use App\Models\Building;
use App\Models\MaintenanceRecord;
use Filament\Support\RawJs;
use Filament\Widgets\ChartWidget;
use Filament\Widgets\Concerns\InteractsWithPageFilters;

class BuildingExpenseChartWidget extends ChartWidget
{
    use InteractsWithPageFilters;

    protected static bool $isLazy = false;

    protected static ?int $sort = 3;

    protected static ?string $heading = 'Perbandingan Biaya Servis per Gedung';

    protected static ?string $description = 'Total biaya servis selesai, termasuk jasa dan sparepart.';

    protected static ?string $maxHeight = '300px';

    protected function getType(): string
    {
        return 'bar';
    }

    protected function getData(): array
    {
        $buildings = Building::query()
            ->with('floors.rooms.airConditioners')
            ->orderBy('name')
            ->get();

        if ($buildings->isEmpty()) {
            return [
                'labels' => ['Belum ada data biaya servis'],
                'datasets' => [[
                    'label' => 'Total biaya servis',
                    'data' => [0],
                    'backgroundColor' => '#38bdf8',
                    'borderColor' => '#0284c7',
                    'borderWidth' => 1,
                    'borderRadius' => 6,
                ]],
            ];
        }

        $expenses = $buildings->map(function (Building $building): float {
            $airConditionerIds = $building->floors
                ->flatMap(fn ($floor) => $floor->rooms)
                ->flatMap(fn ($room) => $room->airConditioners)
                ->pluck('id');

            if ($airConditionerIds->isEmpty()) {
                return 0.0;
            }

            $records = MaintenanceRecord::query()
                ->where('status', 'selesai')
                ->whereIn('air_conditioner_id', $airConditionerIds)
                ->when(
                    $this->filters['start_date'] ?? null,
                    fn ($query, string $date) => $query->whereDate('performed_at', '>=', $date),
                )
                ->when(
                    $this->filters['end_date'] ?? null,
                    fn ($query, string $date) => $query->whereDate('performed_at', '<=', $date),
                )
                ->with('maintenanceSpareParts')
                ->get();

            return round($records->sum(
                fn (MaintenanceRecord $record): float => (float) $record->labor_cost
                    + (float) $record->maintenanceSpareParts->sum('subtotal'),
            ), 2);
        });

        return [
            'labels' => $buildings->pluck('name')->all(),
            'datasets' => [
                [
                    'label' => 'Total biaya servis',
                    'data' => $expenses->all(),
                    'backgroundColor' => '#3b82f6',
                    'borderColor' => '#1d4ed8',
                    'borderWidth' => 1,
                    'borderRadius' => 6,
                ],
            ],
        ];
    }

    protected function getOptions(): array
    {
        return [
            'maintainAspectRatio' => false,
            'plugins' => [
                'legend' => ['display' => false],
                'tooltip' => [
                    'callbacks' => [
                        'label' => RawJs::make("function(context) { return 'Rp ' + new Intl.NumberFormat('id-ID', { maximumFractionDigits: 0 }).format(context.parsed.y); }"),
                    ],
                ],
            ],
            'scales' => [
                'y' => [
                    'beginAtZero' => true,
                    'ticks' => [
                        'precision' => 0,
                        'callback' => RawJs::make("function(value) { return 'Rp ' + new Intl.NumberFormat('id-ID', { notation: 'compact', maximumFractionDigits: 1 }).format(value); }"),
                    ],
                ],
                'x' => [
                    'ticks' => [
                        'autoSkip' => false,
                        'maxRotation' => 35,
                        'minRotation' => 0,
                    ],
                ],
            ],
        ];
    }

    public function updatedFilters(): void
    {
        $this->cachedData = null;
        $this->updateChartData();
    }
}
