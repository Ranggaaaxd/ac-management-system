<?php

namespace App\Filament\Resources\MaintenanceRecordResource\Pages;

use App\Filament\Resources\MaintenanceRecordResource;
use App\Models\MaintenanceRecord;
use App\Services\InvoiceBilling;
use App\Services\MaintenanceInventory;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Validation\ValidationException;

class EditMaintenanceRecord extends EditRecord
{
    protected static string $resource = MaintenanceRecordResource::class;

    protected ?bool $hasDatabaseTransactions = true;

    /**
     * @var array{air_conditioner_id: int, status: string, items: array<int, int>}|null
     */
    private ?array $previousState = null;

    protected function mutateFormDataBeforeSave(array $data): array
    {
        if ($this->getRecord()->invoice_id && ($data['status'] ?? null) !== 'selesai') {
            throw ValidationException::withMessages([
                'data.status' => 'Servis yang sudah ditagihkan harus tetap berstatus selesai.',
            ]);
        }

        return $data;
    }

    protected function beforeSave(): void
    {
        $record = MaintenanceRecord::query()
            ->lockForUpdate()
            ->findOrFail($this->getRecord()->getKey());

        $this->previousState = app(MaintenanceInventory::class)->snapshot($record);
    }

    protected function afterSave(): void
    {
        app(MaintenanceInventory::class)->reconcile($this->previousState, $this->getRecord());

        if ($invoiceId = $this->getRecord()->invoice_id) {
            app(InvoiceBilling::class)->refresh($invoiceId);
        }
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make()
                ->action(function (MaintenanceRecord $record): void {
                    $invoiceId = $record->invoice_id;
                    app(MaintenanceInventory::class)->delete($record);

                    if ($invoiceId) {
                        app(InvoiceBilling::class)->refresh($invoiceId);
                    }
                })
                ->successRedirectUrl(MaintenanceRecordResource::getUrl('index')),
        ];
    }
}
