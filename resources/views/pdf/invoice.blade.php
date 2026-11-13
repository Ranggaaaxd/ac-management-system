<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>Invoice {{ $invoice->invoice_number }}</title>
    <style>
        @page { margin: 35px 40px; }
        body { font-family: DejaVu Sans, sans-serif; color: #222; font-size: 11px; }
        h1 { margin: 0 0 4px; font-size: 22px; }
        h2 { margin: 22px 0 8px; font-size: 14px; }
        p { margin: 4px 0; }
        table { width: 100%; border-collapse: collapse; }
        .header td { vertical-align: top; padding-bottom: 14px; border-bottom: 2px solid #222; }
        .company { width: 65%; }
        .company-logo { max-width: 90px; max-height: 60px; margin-bottom: 6px; }
        .meta { margin-top: 18px; }
        .meta td { padding: 3px 0; vertical-align: top; }
        .items { margin-top: 12px; }
        .items th, .items td { border: 1px solid #bbb; padding: 7px; vertical-align: top; }
        .items th { background: #f0f0f0; text-align: left; }
        .number { text-align: right; white-space: nowrap; }
        .total { margin-top: 12px; }
        .total td { padding: 8px; font-weight: bold; border-top: 2px solid #222; }
        .notes { margin-top: 20px; padding: 10px; border: 1px solid #bbb; }
        .signatures { margin-top: 55px; }
        .signatures td { width: 50%; text-align: center; vertical-align: top; }
        .signature-space { height: 65px; }
        .muted { color: #666; }
    </style>
</head>
<body>
    @php
        $companyName = $companySetting?->company_name ?: config('app.name', 'Perusahaan');
    @endphp
    <table class="header">
        <tr>
            <td class="company">
                @if ($companyLogo)
                    <img class="company-logo" src="{{ $companyLogo }}" alt="Logo {{ $companyName }}">
                @endif
                <h1>{{ $companyName }}</h1>
                @if ($companySetting?->company_address)
                    <p>{{ $companySetting->company_address }}</p>
                @endif
                <p class="muted">
                    {{ $companySetting?->company_phone ?? '' }}
                    {{ $companySetting?->company_email ? ' | '.$companySetting->company_email : '' }}
                </p>
                <p><strong>INVOICE</strong> — Tagihan pekerjaan pemeliharaan dan perbaikan AC</p>
            </td>
            <td class="number">
                <strong>{{ $invoice->invoice_number }}</strong><br>
                Tanggal: {{ $invoice->invoice_date?->format('d/m/Y') }}<br>
                Jatuh tempo: {{ $invoice->due_date?->format('d/m/Y') ?? '—' }}
            </td>
        </tr>
    </table>

    @if ($companySetting?->bank_name || $companySetting?->bank_account_number || $companySetting?->bank_account_holder)
        <div class="notes">
            <strong>Informasi pembayaran</strong><br>
            {{ $companySetting->bank_name ?? '' }}
            {{ $companySetting->bank_account_number ? ' — '.$companySetting->bank_account_number : '' }}
            {{ $companySetting->bank_account_holder ? ' a.n. '.$companySetting->bank_account_holder : '' }}
        </div>
    @endif

    <table class="meta">
        <tr>
            <td style="width: 18%;"><strong>Ditagihkan kepada</strong></td>
            <td>
                {{ $invoice->building?->name ?? 'Pelanggan' }}<br>
                {{ $invoice->building?->address ?? '' }}
            </td>
        </tr>
        <tr>
            <td><strong>Status pembayaran</strong></td>
            <td>{{ \App\Filament\Resources\InvoiceResource::paymentStatusOptions()[$invoice->payment_status] ?? $invoice->payment_status }}</td>
        </tr>
    </table>

    <h2>Rincian Pekerjaan</h2>
    <table class="items">
        <thead>
            <tr>
                <th style="width: 5%;">No.</th>
                <th style="width: 17%;">Tanggal / Aset</th>
                <th style="width: 29%;">Lokasi / Pekerjaan</th>
                <th style="width: 20%;">Sparepart</th>
                <th class="number" style="width: 13%;">Jasa</th>
                <th class="number" style="width: 16%;">Jumlah</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($invoice->maintenanceRecords as $index => $record)
                @php
                    $airConditioner = $record->airConditioner;
                    $partsTotal = (float) $record->maintenanceSpareParts->sum('subtotal');
                @endphp
                <tr>
                    <td>{{ $index + 1 }}</td>
                    <td>
                        {{ $record->performed_at?->format('d/m/Y') }}<br>
                        <strong>{{ $airConditioner->asset_code }}</strong><br>
                        {{ $airConditioner->brand }} ({{ $airConditioner->capacity_pk }} PK)
                    </td>
                    <td>
                        {{ $airConditioner->room->floor->building->name }}<br>
                        {{ $airConditioner->room->floor->name }} — {{ $airConditioner->room->name }}<br>
                        {{ str($record->type)->replace('_', ' ')->title() }}<br>
                        <span class="muted">{{ $record->action_taken ?: $record->issue_description ?: '—' }}</span>
                    </td>
                    <td>
                        @forelse ($record->maintenanceSpareParts as $item)
                            {{ $item->sparePart->name }} ({{ $item->quantity }} {{ $item->sparePart->unit }})<br>
                        @empty
                            —
                        @endforelse
                        <br><strong>Subtotal: Rp {{ number_format($partsTotal, 0, ',', '.') }}</strong>
                    </td>
                    <td class="number">Rp {{ number_format((float) $record->labor_cost, 0, ',', '.') }}</td>
                    <td class="number">Rp {{ number_format((float) $record->total_cost, 0, ',', '.') }}</td>
                </tr>
            @empty
                <tr><td colspan="6" style="text-align: center;">Belum ada pekerjaan yang ditagihkan.</td></tr>
            @endforelse
        </tbody>
    </table>

    <table class="total">
        <tr>
            <td class="number">GRAND TOTAL</td>
            <td class="number" style="width: 25%;">Rp {{ number_format((float) $invoice->total_amount, 0, ',', '.') }}</td>
        </tr>
    </table>

    @if ($invoice->notes)
        <div class="notes">
            <strong>Catatan / instruksi pembayaran</strong><br>
            {!! nl2br(e($invoice->notes)) !!}
        </div>
    @endif

    <table class="signatures">
        <tr>
            <td>
                {{ $companySetting?->signatory_title ?: 'Hormat kami' }},
                <div class="signature-space"></div>
                (<strong>{{ $companySetting?->signatory_name ?: '________________________' }}</strong>)
            </td>
            <td>
                {{ $invoice->building?->name ?? 'Pelanggan' }},
                <div class="signature-space"></div>
                (________________________)
            </td>
        </tr>
    </table>
</body>
</html>
