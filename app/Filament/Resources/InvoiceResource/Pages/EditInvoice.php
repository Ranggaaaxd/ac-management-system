<?php

namespace App\Filament\Resources\InvoiceResource\Pages;

use App\Filament\Resources\InvoiceResource;
use App\Models\Invoice;
use App\Services\InvoiceBilling;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditInvoice extends EditRecord
{
    protected static string $resource = InvoiceResource::class;

    protected ?bool $hasDatabaseTransactions = true;

    /**
     * @var array<int, int>
     */
    private array $maintenanceRecordIds = [];

    protected function mutateFormDataBeforeFill(array $data): array
    {
        $record = $this->getRecord();
        $recordIds = $record->maintenanceRecords()->pluck('id')->map(fn ($id): string => (string) $id)->all();

        $data['maintenance_record_ids'] = $recordIds;
        $data['total_amount'] = app(InvoiceBilling::class)->totalFor($recordIds);

        return $data;
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $this->maintenanceRecordIds = array_map('intval', $data['maintenance_record_ids'] ?? []);
        unset($data['maintenance_record_ids']);
        unset($data['total_amount']);

        return $data;
    }

    protected function afterSave(): void
    {
        app(InvoiceBilling::class)->sync($this->getRecord(), $this->maintenanceRecordIds);
    }

    protected function getHeaderActions(): array
    {
        return [
            InvoiceResource::pdfHeaderAction('printInvoice', 'Cetak Invoice (PDF)', 'heroicon-o-document-text', 'invoice'),
            InvoiceResource::pdfHeaderAction('printBast', 'Cetak BAST (PDF)', 'heroicon-o-clipboard-document-check', 'bast'),
            Actions\DeleteAction::make()
                ->before(function (Invoice $record): void {
                    $record->maintenanceRecords()->update(['invoice_id' => null]);
                }),
        ];
    }
}
