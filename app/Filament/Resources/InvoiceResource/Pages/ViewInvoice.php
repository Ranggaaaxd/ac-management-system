<?php

namespace App\Filament\Resources\InvoiceResource\Pages;

use App\Filament\Resources\InvoiceResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

class ViewInvoice extends ViewRecord
{
    protected static string $resource = InvoiceResource::class;

    protected function getHeaderActions(): array
    {
        return [
            InvoiceResource::pdfHeaderAction('printInvoice', 'Cetak Invoice (PDF)', 'heroicon-o-document-text', 'invoice'),
            InvoiceResource::pdfHeaderAction('printBast', 'Cetak BAST (PDF)', 'heroicon-o-clipboard-document-check', 'bast'),
            Actions\EditAction::make(),
        ];
    }
}
