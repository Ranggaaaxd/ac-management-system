<?php

namespace App\Filament\Widgets;

use App\Models\AirConditioner;
use Filament\Widgets\ChartWidget;
use Filament\Widgets\Concerns\InteractsWithPageFilters;
use Illuminate\Database\Eloquent\Builder;

class AcConditionChartWidget extends ChartWidget
{
    use InteractsWithPageFilters;

    protected static bool $isLazy = false;

    protected static ?int $sort = 2;

    protected static ?string $heading = 'Komposisi Kondisi AC';

    protected static ?string $description = 'Kondisi unit dengan riwayat servis dalam rentang tanggal terpilih.';

    protected static ?string $maxHeight = '260px';

    protected function getType(): string
    {
        return 'doughnut';
    }

    protected function getData(): array
    {
        $startDate = $this->filters['start_date'] ?? null;
        $endDate = $this->filters['end_date'] ?? null;

        $airConditioners = AirConditioner::query()
            ->when(
                $startDate || $endDate,
                fn (Builder $query): Builder => $query->whereHas(
                    'maintenanceRecords',
                    fn (Builder $query): Builder => $query
                        ->when($startDate ?? null, fn (Builder $query, string $date): Builder => $query->whereDate('performed_at', '>=', $date))
                        ->when($endDate ?? null, fn (Builder $query, string $date): Builder => $query->whereDate('performed_at', '<=', $date)),
                ),
            );

        $conditions = $airConditioners
            ->selectRaw('`condition`, COUNT(*) as aggregate')
            ->groupBy('condition')
            ->pluck('aggregate', 'condition');

        return [
            'labels' => ['Baik', 'Rusak Ringan', 'Rusak Berat', 'Perlu Servis'],
            'datasets' => [
                [
                    'data' => [
                        (int) ($conditions['baik'] ?? 0),
                        (int) ($conditions['rusak_ringan'] ?? 0),
                        (int) ($conditions['rusak_berat'] ?? 0),
                        (int) ($conditions['perlu_servis'] ?? 0),
                    ],
                    'backgroundColor' => ['#16a34a', '#f59e0b', '#dc2626', '#2563eb'],
                    'borderColor' => ['#16a34a', '#f59e0b', '#dc2626', '#2563eb'],
                ],
            ],
        ];
    }

    protected function getOptions(): array
    {
        return [
            'cutout' => '68%',
            'plugins' => [
                'legend' => [
                    'position' => 'bottom',
                ],
            ],
            'scales' => [
                'x' => ['display' => false],
                'y' => ['display' => false],
            ],
            'maintainAspectRatio' => false,
        ];
    }

    public function updatedFilters(): void
    {
        $this->cachedData = null;
        $this->updateChartData();
    }
}
