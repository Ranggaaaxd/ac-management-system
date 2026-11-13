<?php

namespace App\Services;

use App\Models\AirConditioner;
use App\Models\Building;
use App\Models\Floor;
use App\Models\Room;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use PhpOffice\PhpSpreadsheet\IOFactory;

class AirConditionerSpreadsheetImporter
{
    private const HEADERS = [
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
    ];

    private const AC_TYPES = [
        'split_wall',
        'cassette',
        'standing_floor',
        'split_duct',
        'vrv_vrf',
    ];

    private const CONDITIONS = [
        'baik',
        'rusak_ringan',
        'rusak_berat',
        'perlu_servis',
    ];

    /**
     * @return array{imported: int, errors: list<string>}
     */
    public function import(string $path): array
    {
        $spreadsheet = IOFactory::load($path);

        try {
            $rows = $spreadsheet->getActiveSheet()->toArray(null, true, true, false);
        } finally {
            $spreadsheet->disconnectWorksheets();
        }

        if ($rows === []) {
            throw ValidationException::withMessages([
                'file' => 'File yang diunggah tidak memiliki baris header.',
            ]);
        }

        $headers = array_map(function (mixed $header): string {
            $header = preg_replace('/^\xEF\xBB\xBF/', '', (string) $header);

            return Str::lower(trim($header));
        }, array_shift($rows));

        if (array_diff(self::HEADERS, $headers) !== []) {
            throw ValidationException::withMessages([
                'file' => 'Header file tidak sesuai. Gunakan template Data AC yang disediakan.',
            ]);
        }

        $headerIndexes = array_flip($headers);
        $imported = 0;
        $errors = [];

        foreach ($rows as $index => $row) {
            $line = $index + 2;
            $values = [];

            foreach (self::HEADERS as $header) {
                $values[$header] = trim((string) ($row[$headerIndexes[$header]] ?? ''));
            }

            if (count(array_filter($values, fn (string $value): bool => $value !== '')) === 0) {
                continue;
            }

            $validationError = $this->validateRow($values);

            if ($validationError !== null) {
                $errors[] = "Baris {$line}: {$validationError}";

                continue;
            }

            if ($values['kode_aset'] !== '' && AirConditioner::query()->where('asset_code', $values['kode_aset'])->exists()) {
                $errors[] = "Baris {$line}: kode aset {$values['kode_aset']} sudah digunakan.";

                continue;
            }

            DB::transaction(function () use ($values): void {
                $building = Building::query()->firstOrCreate(['name' => $values['gedung']]);
                $floor = Floor::query()->firstOrCreate(
                    [
                        'building_id' => $building->id,
                        'name' => $values['lantai'],
                    ],
                    ['floor_number' => $this->floorNumber($values['lantai'])],
                );
                $room = Room::query()->firstOrCreate([
                    'floor_id' => $floor->id,
                    'name' => $values['ruangan'],
                ]);

                AirConditioner::query()->create([
                    'room_id' => $room->id,
                    'asset_code' => $values['kode_aset'] !== '' ? $values['kode_aset'] : $this->generateAssetCode(),
                    'brand' => $values['merk'],
                    'ac_type' => $values['tipe_ac'] !== '' ? $values['tipe_ac'] : 'split_wall',
                    'capacity_pk' => $values['pk'] !== '' ? $values['pk'] : '1',
                    'refrigerant_type' => $values['jenis_refrigeran'] !== '' ? $values['jenis_refrigeran'] : 'R32',
                    'production_year' => $values['tahun_produksi'] !== '' ? (int) $values['tahun_produksi'] : null,
                    'condition' => $values['kondisi'] !== '' ? $values['kondisi'] : 'baik',
                ]);
            });

            $imported++;
        }

        if ($imported === 0 && $errors === []) {
            $errors[] = 'Tidak ada baris data untuk diimpor.';
        }

        return [
            'imported' => $imported,
            'errors' => $errors,
        ];
    }

    /**
     * @param  array<string, string>  $values
     */
    private function validateRow(array $values): ?string
    {
        foreach (['gedung', 'lantai', 'ruangan', 'merk', 'pk'] as $required) {
            if ($values[$required] === '') {
                return "kolom {$required} wajib diisi.";
            }
        }

        if ($values['tipe_ac'] !== '' && ! in_array($values['tipe_ac'], self::AC_TYPES, true)) {
            return "tipe_ac tidak valid: {$values['tipe_ac']}.";
        }

        if ($values['kondisi'] !== '' && ! in_array($values['kondisi'], self::CONDITIONS, true)) {
            return "kondisi tidak valid: {$values['kondisi']}.";
        }

        if ($values['tahun_produksi'] !== ''
            && (! ctype_digit($values['tahun_produksi'])
                || (int) $values['tahun_produksi'] < 1900
                || (int) $values['tahun_produksi'] > (int) date('Y') + 1)) {
            return "tahun_produksi tidak valid: {$values['tahun_produksi']}.";
        }

        return null;
    }

    private function floorNumber(string $floorName): int
    {
        return preg_match('/-?\d+/', $floorName, $matches) === 1
            ? (int) $matches[0]
            : 0;
    }

    private function generateAssetCode(): string
    {
        do {
            $assetCode = 'AC-'.Str::upper(Str::random(8));
        } while (AirConditioner::query()->where('asset_code', $assetCode)->exists());

        return $assetCode;
    }
}
