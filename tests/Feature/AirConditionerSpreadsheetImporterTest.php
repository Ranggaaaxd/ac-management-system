<?php

namespace Tests\Feature;

use App\Models\AirConditioner;
use App\Models\Building;
use App\Models\Floor;
use App\Models\Room;
use App\Services\AirConditionerSpreadsheetImporter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\TestCase;

class AirConditionerSpreadsheetImporterTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_imports_csv_creates_location_and_applies_defaults(): void
    {
        $result = $this->importCsv([
            ['GPS', '1', 'Lab Komputer', '', 'Daikin', '', '1', '', '2022', ''],
        ]);

        $this->assertSame(1, $result['imported']);
        $this->assertSame([], $result['errors']);
        $this->assertDatabaseHas('buildings', ['name' => 'GPS']);
        $this->assertDatabaseHas('floors', ['name' => '1', 'floor_number' => 1]);
        $this->assertDatabaseHas('rooms', ['name' => 'Lab Komputer']);

        $airConditioner = AirConditioner::query()->firstOrFail();
        $this->assertMatchesRegularExpression('/^AC-[A-Z0-9]{8}$/', $airConditioner->asset_code);
        $this->assertSame('split_wall', $airConditioner->ac_type);
        $this->assertSame('1', $airConditioner->capacity_pk);
        $this->assertSame('R32', $airConditioner->refrigerant_type);
        $this->assertSame('baik', $airConditioner->condition);
        $this->assertSame(2022, $airConditioner->production_year);
    }

    public function test_it_skips_duplicate_codes_and_reports_invalid_rows_without_stopping_import(): void
    {
        $this->createExistingAirConditioner('AC-GPS-001');

        $result = $this->importCsv([
            ['GPS', '1', 'Lab Komputer', 'AC-GPS-001', 'Daikin', 'split_wall', '1', 'R32', '2022', 'baik'],
            ['GPS', '1', 'Ruang Dosen', 'AC-GPS-002', 'Panasonic', 'cassette', '2', 'R410A', '2021', 'baik'],
            ['GPS', '1', 'Ruang Bermasalah', 'AC-GPS-003', 'LG', 'tipe_tidak_ada', '1', 'R32', '2022', 'baik'],
        ]);

        $this->assertSame(1, $result['imported']);
        $this->assertCount(2, $result['errors']);
        $this->assertDatabaseHas('air_conditioners', ['asset_code' => 'AC-GPS-001']);
        $this->assertDatabaseHas('air_conditioners', ['asset_code' => 'AC-GPS-002']);
        $this->assertDatabaseMissing('air_conditioners', ['asset_code' => 'AC-GPS-003']);
        $this->assertCount(2, Building::query()->get());
    }

    public function test_it_imports_xlsx_files(): void
    {
        $path = sys_get_temp_dir().DIRECTORY_SEPARATOR.'ac-import-'.uniqid().'.xlsx';
        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->fromArray([
            ['gedung', 'lantai', 'ruangan', 'kode_aset', 'merk', 'tipe_ac', 'pk', 'jenis_refrigeran', 'tahun_produksi', 'kondisi'],
            ['GPS', '2', 'Ruang Dosen', 'AC-GPS-002', 'Panasonic', 'cassette', '2', 'R410A', '2021', 'baik'],
        ]);
        (new Xlsx($spreadsheet))->save($path);

        try {
            $result = app(AirConditionerSpreadsheetImporter::class)->import($path);
        } finally {
            $spreadsheet->disconnectWorksheets();
            @unlink($path);
        }

        $this->assertSame(1, $result['imported']);
        $this->assertSame([], $result['errors']);
        $this->assertDatabaseHas('air_conditioners', [
            'asset_code' => 'AC-GPS-002',
            'brand' => 'Panasonic',
            'ac_type' => 'cassette',
            'capacity_pk' => '2',
        ]);
    }

    /**
     * @param  list<list<string>>  $dataRows
     * @return array{imported: int, errors: list<string>}
     */
    private function importCsv(array $dataRows): array
    {
        $temporaryPath = tempnam(sys_get_temp_dir(), 'ac-import-');
        $path = $temporaryPath.'.csv';
        rename($temporaryPath, $path);
        $handle = fopen($path, 'wb');

        try {
            fwrite($handle, "\xEF\xBB\xBF");
            fputcsv($handle, [
                'gedung',
                'lantai',
                'ruangan',
                'kode_aset',
                'merk',
                'tipe_ac',
                'pk',
                'jenis_refrigeran',
                'tahun_produksi',
                'kondisi',
            ]);

            foreach ($dataRows as $row) {
                fputcsv($handle, $row);
            }

            fclose($handle);

            return app(AirConditionerSpreadsheetImporter::class)->import($path);
        } finally {
            if (is_resource($handle)) {
                fclose($handle);
            }

            @unlink($path);
        }
    }

    private function createExistingAirConditioner(string $assetCode): void
    {
        $building = Building::query()->create(['name' => 'Gedung Lama']);
        $floor = Floor::query()->create([
            'building_id' => $building->id,
            'name' => '1',
            'floor_number' => 1,
        ]);
        $room = Room::query()->create([
            'floor_id' => $floor->id,
            'name' => 'Ruang Lama',
        ]);

        AirConditioner::query()->create([
            'room_id' => $room->id,
            'asset_code' => $assetCode,
            'brand' => 'Daikin',
            'capacity_pk' => '1',
        ]);
    }
}
