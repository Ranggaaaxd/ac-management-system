<?php

namespace App\Filament\Resources\AirConditionerResource\Pages;

use App\Filament\Resources\AirConditionerResource;
use App\Services\AirConditionerSpreadsheetImporter;
use Filament\Actions;
use Filament\Forms\Components\FileUpload;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Support\Facades\Response;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class ListAirConditioners extends ListRecords
{
    protected static string $resource = AirConditionerResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('downloadTemplate')
                ->label('Download Template Excel')
                ->icon('heroicon-m-arrow-down-tray')
                ->action(function () {
                    $spreadsheet = new Spreadsheet;
                    $sheet = $spreadsheet->getActiveSheet();
                    $sheet->fromArray([
                        ['gedung', 'lantai', 'ruangan', 'kode_aset', 'merk', 'tipe_ac', 'pk', 'jenis_refrigeran', 'tahun_produksi', 'kondisi'],
                        ['GPS', '1', 'Lab Komputer', 'AC-GPS-001', 'Daikin', 'split_wall', '1', 'R32', '2022', 'baik'],
                        ['GPS', '2', 'Ruang Dosen', 'AC-GPS-002', 'Panasonic', 'cassette', '2', 'R410A', '2021', 'baik'],
                    ]);
                    $sheet->getStyle('A1:J1')->getFont()->setBold(true);

                    foreach (range('A', 'J') as $column) {
                        $sheet->getColumnDimension($column)->setAutoSize(true);
                    }

                    return Response::streamDownload(function () use ($spreadsheet): void {
                        try {
                            (new Xlsx($spreadsheet))->save('php://output');
                        } finally {
                            $spreadsheet->disconnectWorksheets();
                        }
                    }, 'template-data-ac.xlsx', [
                        'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                    ]);
                }),
            Actions\Action::make('importExcel')
                ->label('Import Excel')
                ->icon('heroicon-m-arrow-up-tray')
                ->color('success')
                ->form([
                    FileUpload::make('file')
                        ->label('File Excel/CSV')
                        ->acceptedFileTypes([
                            'text/csv',
                            'text/plain',
                            'application/vnd.ms-excel',
                            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                        ])
                        ->rules(['extensions:xlsx,xls,csv'])
                        ->storeFiles(false)
                        ->required(),
                ])
                ->action(function (array $data): void {
                    $file = $data['file'];

                    if (! $file instanceof TemporaryUploadedFile) {
                        throw new \RuntimeException('File import tidak tersedia untuk dibaca.');
                    }

                    $result = app(AirConditionerSpreadsheetImporter::class)->import($file->getRealPath());
                    $notification = Notification::make()
                        ->title("{$result['imported']} Data AC berhasil diimpor");

                    if ($result['errors'] !== []) {
                        $notification
                            ->body(implode("\n", array_slice($result['errors'], 0, 5)))
                            ->warning();
                    } else {
                        $notification->success();
                    }

                    $notification->send();
                }),
            Actions\CreateAction::make(),
        ];
    }
}
