<?php

namespace App\Observers;

use App\Models\MaintenanceRecord;
use App\Services\ActivityLogger;

class MaintenanceRecordObserver
{
    public function created(MaintenanceRecord $maintenanceRecord): void
    {
        $assetCode = $maintenanceRecord->airConditioner()->value('asset_code') ?? 'unit tidak diketahui';
        $status = $maintenanceRecord->status === 'selesai' ? 'selesai' : 'draft';

        app(ActivityLogger::class)->log(
            $maintenanceRecord->status === 'selesai' ? 'STATUS_CHANGE' : 'CREATE',
            'Servis',
            "Servis {$assetCode} dibuat dengan status {$status}.",
        );
    }

    public function updated(MaintenanceRecord $maintenanceRecord): void
    {
        $changes = array_diff_key($maintenanceRecord->getChanges(), ['updated_at' => true]);

        if ($changes === []) {
            return;
        }

        $assetCode = $maintenanceRecord->airConditioner()->value('asset_code') ?? 'unit tidak diketahui';
        $statusChanged = array_key_exists('status', $changes);
        $description = $statusChanged
            ? "Status servis {$assetCode} berubah menjadi {$changes['status']}."
            : "Data servis {$assetCode} diperbarui. Field berubah: ".implode(', ', array_keys($changes)).'.';

        app(ActivityLogger::class)->log(
            $statusChanged ? 'STATUS_CHANGE' : 'UPDATE',
            'Servis',
            $description,
        );
    }
}
