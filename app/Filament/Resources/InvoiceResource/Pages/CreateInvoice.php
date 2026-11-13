<?php

namespace App\Filament\Resources\InvoiceResource\Pages;

use App\Filament\Resources\InvoiceResource;
use App\Services\InvoiceBilling;
use Filament\Resources\Pages\CreateRecord;

class CreateInvoice extends CreateRecord
{
    protected static string $resource = InvoiceResource::class;

    protected ?bool $hasDatabaseTransactions = true;

    /**
     * @var array<int, int>
     */
    private array $maintenanceRecordIds = [];

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $this->maintenanceRecordIds = array_map('intval', $data['maintenance_record_ids'] ?? []);
        unset($data['maintenance_record_ids']);
        $data['total_amount'] = 0;

        return $data;
    }

    protected function afterCreate(): void
    {
        app(InvoiceBilling::class)->sync($this->getRecord(), $this->maintenanceRecordIds);
    }
}
