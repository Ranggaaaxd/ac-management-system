<?php

namespace App\Http\Controllers;

use App\Models\CompanySetting;
use App\Models\Invoice;
use Barryvdh\DomPDF\Facade\Pdf;
use Filament\Facades\Filament;
use Filament\Models\Contracts\FilamentUser;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class InvoicePdfController extends Controller
{
    public function invoice(Invoice $invoice): Response
    {
        $this->authorizePanelAccess();
        $invoice->load($this->invoiceRelations());
        $companySetting = CompanySetting::query()->first();

        return Pdf::loadView('pdf.invoice', [
            'invoice' => $invoice,
            'companySetting' => $companySetting,
            'companyLogo' => $this->photoDataUri($companySetting?->logo_path),
        ])
            ->setPaper('a4')
            ->download('invoice-'.Str::slug($invoice->invoice_number).'.pdf');
    }

    public function bast(Invoice $invoice): Response
    {
        $this->authorizePanelAccess();
        $invoice->load($this->invoiceRelations());
        $companySetting = CompanySetting::query()->first();

        $photos = [];

        foreach ($invoice->maintenanceRecords as $record) {
            $photos[$record->id] = [
                'before' => $this->photoDataUri($record->before_photo),
                'after' => $this->photoDataUri($record->after_photo),
            ];
        }

        return Pdf::loadView('pdf.bast', [
            'invoice' => $invoice,
            'photos' => $photos,
            'companySetting' => $companySetting,
            'companyLogo' => $this->photoDataUri($companySetting?->logo_path),
        ])
            ->setPaper('a4')
            ->download('bast-'.Str::slug($invoice->invoice_number).'.pdf');
    }

    private function authorizePanelAccess(): void
    {
        $panel = Filament::getPanel('admin');
        Filament::setCurrentPanel($panel);
        $user = Filament::auth()->user();

        abort_unless(
            $user && (
                $user instanceof FilamentUser
                    ? $user->canAccessPanel($panel)
                    : config('app.env') === 'local'
            ),
            403,
        );
    }

    /**
     * @return array<string>
     */
    private function invoiceRelations(): array
    {
        return [
            'building',
            'maintenanceRecords.airConditioner.room.floor.building',
            'maintenanceRecords.technician',
            'maintenanceRecords.maintenanceSpareParts.sparePart',
        ];
    }

    private function photoDataUri(?string $path): ?string
    {
        if (blank($path) || ! Storage::disk('public')->exists($path)) {
            return null;
        }

        $contents = Storage::disk('public')->get($path);
        $mimeType = Storage::disk('public')->mimeType($path) ?: 'image/jpeg';

        if (! in_array($mimeType, ['image/jpeg', 'image/png', 'image/gif'], true)) {
            return null;
        }

        return 'data:'.$mimeType.';base64,'.base64_encode($contents);
    }
}
