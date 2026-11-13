<?php

namespace App\Filament\Pages;

use App\Models\AirConditioner;
use App\Models\Building;
use App\Models\MaintenanceRecord;
use Filament\Actions\Action;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Form;
use Filament\Pages\Page;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Response;
use Illuminate\Validation\ValidationException;
use Livewire\WithPagination;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

class BuildingReport extends Page implements HasForms
{
    use InteractsWithForms;
    use WithPagination;

    protected static ?string $navigationGroup = 'Data AC';

    protected static ?string $navigationIcon = 'heroicon-o-document-chart-bar';

    protected static ?string $navigationLabel = 'Laporan per Gedung';

    protected static ?string $title = 'Laporan per Gedung';

    protected static ?int $navigationSort = 1;

    protected static string $view = 'filament.pages.building-report';

    public ?array $data = [];

    public function mount(): void
    {
        $this->form->fill([
            'building_id' => null,
            'start_date' => now()->startOfMonth()->toDateString(),
            'end_date' => now()->endOfMonth()->toDateString(),
        ]);
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Select::make('building_id')
                    ->label('Gedung')
                    ->options(fn (): array => Building::query()->orderBy('name')->pluck('name', 'id')->all())
                    ->searchable()
                    ->required()
                    ->live(),
                Forms\Components\DatePicker::make('start_date')
                    ->label('Dari tanggal')
                    ->required()
                    ->live(),
                Forms\Components\DatePicker::make('end_date')
                    ->label('Sampai tanggal')
                    ->required()
                    ->live(),
            ])
            ->columns(3)
            ->statePath('data');
    }

    public function updatedData(): void
    {
        $this->resetPage('airConditionersPage');
        $this->resetPage('maintenanceRecordsPage');
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('exportReport')
                ->label('Export Laporan Excel (.xlsx)')
                ->icon('heroicon-m-arrow-down-tray')
                ->color('success')
                ->disabled(fn (): bool => blank($this->data['building_id'] ?? null))
                ->action(fn (): StreamedResponse => $this->exportReport()),
        ];
    }

    /**
     * @return array{units: int, good: int, damaged: int, services: int, cost: float}
     */
    public function reportSummary(): array
    {
        if (! $this->selectedBuildingId()) {
            return ['units' => 0, 'good' => 0, 'damaged' => 0, 'services' => 0, 'cost' => 0.0];
        }

        $units = $this->airConditionerQuery();
        $services = $this->maintenanceRecordQuery()->get();

        return [
            'units' => (clone $units)->count(),
            'good' => (clone $units)->where('condition', 'baik')->count(),
            'damaged' => (clone $units)->whereIn('condition', [
                'rusak_ringan',
                'rusak_berat',
                'perlu_servis',
            ])->count(),
            'services' => $services->count(),
            'cost' => round($services->sum(fn (MaintenanceRecord $record): float => (float) $record->total_cost), 2),
        ];
    }

    public function airConditionerRows(): LengthAwarePaginator
    {
        return $this->airConditionerQuery()
            ->with(['room.floor'])
            ->orderBy('asset_code')
            ->paginate(10, ['*'], 'airConditionersPage');
    }

    public function maintenanceRecordRows(): LengthAwarePaginator
    {
        return $this->maintenanceRecordQuery()
            ->with(['airConditioner', 'technician', 'maintenanceSpareParts'])
            ->orderByDesc('performed_at')
            ->paginate(10, ['*'], 'maintenanceRecordsPage');
    }

    public function exportReport(): StreamedResponse
    {
        $state = $this->form->getState();
        $building = Building::query()->find($state['building_id'] ?? null);

        if (! $building) {
            throw ValidationException::withMessages([
                'data.building_id' => 'Pilih gedung untuk membuat laporan.',
            ]);
        }

        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Laporan Gedung');
        $rows = [
            ['Laporan per Gedung'],
            ['Gedung', $building->name],
            ['Periode', ($state['start_date'] ?? '').' - '.($state['end_date'] ?? '')],
            [],
            ['Daftar Unit AC'],
            ['Kode AC', 'Lantai', 'Ruangan', 'Merk / PK', 'Kondisi', 'Terakhir Servis'],
        ];

        foreach ($this->airConditionerQuery()->with(['room.floor'])->orderBy('asset_code')->get() as $airConditioner) {
            $rows[] = [
                $airConditioner->asset_code,
                $airConditioner->room->floor->name,
                $airConditioner->room->name,
                "{$airConditioner->brand} / {$airConditioner->capacity_pk} PK",
                $airConditioner->condition,
                $airConditioner->last_serviced_at?->format('Y-m-d') ?? '',
            ];
        }

        $serviceHeadingRow = count($rows) + 2;
        $rows[] = [];
        $rows[] = ['Riwayat Servis Selesai'];
        $rows[] = ['Tanggal', 'Kode AC', 'Tindakan', 'Teknisi', 'Total Biaya'];

        foreach ($this->maintenanceRecordQuery()
            ->with(['airConditioner', 'technician', 'maintenanceSpareParts'])
            ->orderByDesc('performed_at')
            ->get() as $record) {
            $rows[] = [
                $record->performed_at->format('Y-m-d'),
                $record->airConditioner->asset_code,
                $record->action_taken ?: $record->issue_description ?: '',
                $record->technician?->name ?? '',
                (float) $record->total_cost,
            ];
        }

        $sheet->fromArray($rows);
        $lastColumn = $sheet->getHighestColumn();
        $sheet->mergeCells('A1:'.$lastColumn.'1');
        foreach ([1, 5, 6, $serviceHeadingRow, $serviceHeadingRow + 1] as $headerRow) {
            $sheet->getStyle("A{$headerRow}:{$lastColumn}{$headerRow}")
                ->getFont()
                ->setBold();
        }
        $sheet->getStyle('A1')->getFont()->setSize(16);
        $sheet->getStyle("A6:F6")->getFill()
            ->setFillType(Fill::FILL_SOLID)
            ->getStartColor()->setRGB('DBEAFE');
        $sheet->getStyle("A".($serviceHeadingRow + 1).":E".($serviceHeadingRow + 1))->getFill()
            ->setFillType(Fill::FILL_SOLID)
            ->getStartColor()->setRGB('DBEAFE');
        $sheet->getStyle("E".($serviceHeadingRow + 2).":E".$sheet->getHighestRow())
            ->getNumberFormat()
            ->setFormatCode('"Rp" #,##0');
        $sheet->getStyle('A1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);

        foreach (range('A', $lastColumn) as $column) {
            $sheet->getColumnDimension($column)->setAutoSize(true);
        }

        return Response::streamDownload(
            function () use ($spreadsheet): void {
                (new Xlsx($spreadsheet))->save('php://output');
            },
            'laporan-gedung-'.str($building->name)->slug().'-'.now()->format('Y-m-d_His').'.xlsx',
            ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'],
        );
    }

    private function selectedBuildingId(): int|string|null
    {
        return $this->data['building_id'] ?? null;
    }

    private function airConditionerQuery(): Builder
    {
        return AirConditioner::query()
            ->whereHas(
                'room.floor',
                fn (Builder $query): Builder => $query->where('building_id', $this->selectedBuildingId()),
            );
    }

    private function maintenanceRecordQuery(): Builder
    {
        return MaintenanceRecord::query()
            ->where('status', 'selesai')
            ->whereHas(
                'airConditioner.room.floor',
                fn (Builder $query): Builder => $query->where('building_id', $this->selectedBuildingId()),
            )
            ->when(
                $this->data['start_date'] ?? null,
                fn (Builder $query, string $date): Builder => $query->whereDate('performed_at', '>=', $date),
            )
            ->when(
                $this->data['end_date'] ?? null,
                fn (Builder $query, string $date): Builder => $query->whereDate('performed_at', '<=', $date),
            );
    }
}
