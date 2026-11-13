<?php

namespace App\Observers;

use App\Models\Invoice;
use App\Services\ActivityLogger;

class InvoiceObserver
{
    public function updated(Invoice $invoice): void
    {
        $changes = $invoice->getChanges();

        if (! array_key_exists('payment_status', $changes)) {
            return;
        }

        $previousStatus = $invoice->getPrevious()['payment_status'] ?? 'tidak diketahui';

        app(ActivityLogger::class)->log(
            'STATUS_CHANGE',
            'Invoice',
            "Invoice {$invoice->invoice_number}: status pembayaran {$previousStatus} menjadi {$changes['payment_status']}.",
        );
    }
}
