<?php

namespace App\Filament\Resources\MaintenanceRecordResource\Pages;

use App\Filament\Resources\MaintenanceRecordResource;
use App\Services\MaintenanceInventory;
use Filament\Resources\Pages\CreateRecord;

class CreateMaintenanceRecord extends CreateRecord
{
    protected static string $resource = MaintenanceRecordResource::class;

    protected ?bool $hasDatabaseTransactions = true;

    protected function afterFill(): void
    {
        $airConditionerId = request()->query('air_conditioner_id');

        if (filled($airConditionerId)) {
            $this->data['air_conditioner_id'] = $airConditionerId;
        }
    }

    protected function afterCreate(): void
    {
        app(MaintenanceInventory::class)->reconcile(null, $this->getRecord());
    }
}
