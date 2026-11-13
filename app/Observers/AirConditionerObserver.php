<?php

namespace App\Observers;

use App\Models\AirConditioner;
use App\Services\ActivityLogger;

class AirConditionerObserver
{
    public function created(AirConditioner $airConditioner): void
    {
        app(ActivityLogger::class)->log(
            'CREATE',
            'Data AC',
            "Unit {$airConditioner->asset_code} ditambahkan.",
        );
    }

    public function updated(AirConditioner $airConditioner): void
    {
        $changes = array_diff_key($airConditioner->getChanges(), ['updated_at' => true]);

        if ($changes === []) {
            return;
        }

        $changedFields = implode(', ', array_keys($changes));

        app(ActivityLogger::class)->log(
            'UPDATE',
            'Data AC',
            "Unit {$airConditioner->asset_code} diperbarui. Field berubah: {$changedFields}.",
        );
    }

    public function deleted(AirConditioner $airConditioner): void
    {
        app(ActivityLogger::class)->log(
            'DELETE',
            'Data AC',
            "Unit {$airConditioner->asset_code} dihapus.",
        );
    }
}
