<?php

namespace App\Filament\Pages;

use App\Models\AirConditioner;
use App\Models\Building;
use App\Models\MaintenanceRecord;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Pages\Page;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Livewire\WithPagination;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

class BuildingReport extends Page implements HasForms
{
    use InteractsWithForms;
    use WithPagination;

    protected static ?string $navigationIcon = 'heroicon-o-document-chart-bar';

    protected static ?string $navigationLabel = 'Laporan per Gedung';

    protected static ?string $title = 'Laporan Rekapitulasi per Gedung';

    protected static ?string $navigationGroup = 'Data AC';

    protected static ?int $navigationSort = 2;

    protected static string $view = 'filament.pages.building-report';

    public ?array $data = [];

    public function mount(): void
    {
        $firstBuilding = Building::first();

        $this->form->fill([
            'building_id' => $firstBuilding?->id,
            'start_date' => now()->startOfMonth()->toDateString(),
            'end_date' => now()->endOfMonth()->toDateString(),
        ]);
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Section::make('Filter Laporan Gedung')
                    ->schema([
                        Select::make('building_id')
                            ->label('Pilih Gedung')
                            ->options(fn (): array => Building::query()->orderBy('name')->pluck('name', 'id')->all())
                            ->searchable()
                            ->required()
                            ->live(),
                        DatePicker::make('start_date')
                            ->label('Dari Tanggal')
                            ->required()
                            ->live(),
                        DatePicker::make('end_date')
                            ->label('Sampai Tanggal')
                            ->required()
                            ->live(),
                    ])->columns(3),
            ])
            ->statePath('data');
    }

    public function getSelectedBuildingProperty(): ?Building
    {
        $buildingId = $this->data['building_id'] ?? null;

        return $buildingId ? Building::find($buildingId) : null;
    }

    public function selectedBuilding(): ?Building
    {
        return $this->getSelectedBuildingProperty();
    }

    // Menggunakan paginate() agar $unitRows->links() berfungsi normal
    public function getUnitRowsProperty(): LengthAwarePaginator
    {
        $building = $this->selectedBuilding();
        $buildingId = $building?->id ?? 0;

        return AirConditioner::whereHas('room.floor', function ($q) use ($buildingId) {
            $q->where('building_id', $buildingId);
        })
            ->with(['room.floor'])
            ->paginate(10, ['*'], 'unitPage');
    }

    public function unitRows(): LengthAwarePaginator
    {
        return $this->getUnitRowsProperty();
    }

    public function airConditionerRows(): LengthAwarePaginator
    {
        return $this->getUnitRowsProperty();
    }

    // Menggunakan paginate() untuk baris riwayat servis
    public function getServiceRowsProperty(): LengthAwarePaginator
    {
        $building = $this->selectedBuilding();
        $buildingId = $building?->id ?? 0;
        $startDate = $this->data['start_date'] ?? null;
        $endDate = $this->data['end_date'] ?? null;

        return MaintenanceRecord::whereHas('airConditioner.room.floor', function ($q) use ($buildingId) {
            $q->where('building_id', $buildingId);
        })
            ->where('status', 'selesai')
            ->when($startDate && $endDate, function ($q) use ($startDate, $endDate) {
                $q->whereBetween('performed_at', [$startDate, $endDate]);
            })
            ->with(['airConditioner', 'technician', 'maintenanceSpareParts.sparePart'])
            ->latest('performed_at')
            ->paginate(10, ['*'], 'servicePage');
    }

    public function serviceRows(): LengthAwarePaginator
    {
        return $this->getServiceRowsProperty();
    }

    public function maintenanceRows(): LengthAwarePaginator
    {
        return $this->getServiceRowsProperty();
    }

    public function maintenanceRecordRows(): LengthAwarePaginator
    {
        return $this->getServiceRowsProperty();
    }

    // Data ringkasan (cards)
    public function reportSummary(): array
    {
        $building = $this->selectedBuilding();
        $buildingId = $building?->id ?? 0;
        $startDate = $this->data['start_date'] ?? null;
        $endDate = $this->data['end_date'] ?? null;

        $acs = AirConditioner::whereHas('room.floor', function ($q) use ($buildingId) {
            $q->where('building_id', $buildingId);
        })->get();

        $records = MaintenanceRecord::whereHas('airConditioner.room.floor', function ($q) use ($buildingId) {
            $q->where('building_id', $buildingId);
        })
            ->where('status', 'selesai')
            ->when($startDate && $endDate, function ($q) use ($startDate, $endDate) {
                $q->whereBetween('performed_at', [$startDate, $endDate]);
            })
            ->with('maintenanceSpareParts')
            ->get();

        $totalAc = $acs->count();
        $acBaik = $acs->where('condition', 'baik')->count();
        $acRusak = $acs->whereIn('condition', ['rusak_ringan', 'rusak_berat', 'perlu_servis'])->count();
        $totalServis = $records->count();

        $totalBiaya = (float) $records->sum(function ($record) {
            $sparePartsCost = (float) ($record->maintenanceSpareParts?->sum('subtotal') ?? 0);

            return (float) ($record->labor_cost ?? 0) + $sparePartsCost;
        });

        $formattedBiaya = 'Rp '.number_format($totalBiaya, 0, ',', '.');

        return [
            'units' => $totalAc,
            'total_units' => $totalAc,
            'total_ac' => $totalAc,
            'ac' => $totalAc,
            'good' => $acBaik,
            'damaged' => $acRusak,
            'services' => $totalServis,
            'total_services' => $totalServis,
            'cost' => $totalBiaya,
            'total_cost' => $totalBiaya,
            'formatted_cost' => $formattedBiaya,
            'formatted_total_cost' => $formattedBiaya,
            'formatted_biaya' => $formattedBiaya,
        ];
    }

    public function getReportSummaryProperty(): array
    {
        return $this->reportSummary();
    }

    public function exportExcel(): StreamedResponse
    {
        $building = $this->selectedBuilding();
        $buildingName = $building?->name ?? 'Semua';
        $buildingId = $building?->id ?? 0;

        $acs = AirConditioner::whereHas('room.floor', function ($q) use ($buildingId) {
            $q->where('building_id', $buildingId);
        })->with('room.floor')->get();

        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Laporan '.substr($buildingName, 0, 20));

        $sheet->fromArray([
            ['Laporan Rekapitulasi Gedung: '.$buildingName],
            ['Periode: '.($this->data['start_date'] ?? '-').' s/d '.($this->data['end_date'] ?? '-')],
            [],
            ['Kode AC', 'Merk', 'PK', 'Tipe', 'Lantai', 'Ruangan', 'Kondisi'],
        ], null, 'A1');

        $row = 5;
        foreach ($acs as $ac) {
            $sheet->fromArray([
                $ac->asset_code,
                $ac->brand,
                $ac->capacity_pk,
                $ac->ac_type,
                $ac->room?->floor?->name ?? '-',
                $ac->room?->name ?? '-',
                $ac->condition,
            ], null, 'A'.$row);
            $row++;
        }

        $writer = new Xlsx($spreadsheet);
        $fileName = 'laporan-gedung-'.str($buildingName)->slug().'-'.now()->format('YmdHis').'.xlsx';

        return response()->streamDownload(function () use ($writer) {
            $writer->save('php://output');
        }, $fileName, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }
}
