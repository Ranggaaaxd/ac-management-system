<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>BAST {{ $invoice->invoice_number }}</title>
    <style>
        @page { margin: 35px 42px; }
        body { font-family: DejaVu Sans, sans-serif; color: #222; font-size: 11px; }
        h1 { margin: 0; font-size: 17px; text-align: center; }
        h2 { margin: 18px 0 8px; font-size: 13px; }
        p { line-height: 1.55; }
        table { width: 100%; border-collapse: collapse; }
        .heading { margin-bottom: 20px; }
        .heading td { border-bottom: 2px solid #222; padding: 8px; }
        .company-logo { max-width: 80px; max-height: 55px; display: block; margin: 0 auto 6px; }
        .details th, .details td { border: 1px solid #aaa; padding: 7px; text-align: left; vertical-align: top; }
        .details th { width: 28%; background: #f0f0f0; }
        .photos { margin-top: 12px; }
        .photos td { width: 50%; text-align: center; vertical-align: top; padding: 7px; }
        .photos img { max-width: 230px; max-height: 185px; }
        .signatures { margin-top: 48px; }
        .signatures td { width: 50%; text-align: center; vertical-align: top; }
        .signature-space { height: 75px; }
        .page-break { page-break-after: always; }
        .muted { color: #666; }
    </style>
</head>
<body>
    @php
        $companyName = $companySetting?->company_name ?: config('app.name', 'Perusahaan');
    @endphp
    @forelse ($invoice->maintenanceRecords as $index => $record)
        @php
            $airConditioner = $record->airConditioner;
            $building = $airConditioner->room->floor->building;
            $photo = $photos[$record->id] ?? ['before' => null, 'after' => null];
        @endphp
        <table class="heading">
            <tr>
                <td style="text-align: center;">
                    @if ($companyLogo)
                        <img class="company-logo" src="{{ $companyLogo }}" alt="Logo {{ $companyName }}">
                    @endif
                    <strong>{{ $companyName }}</strong><br>
                    {{ $companySetting?->company_address ?? '' }}<br>
                    {{ $companySetting?->company_phone ?? '' }} {{ $companySetting?->company_email ?? '' }}
                    <h1>BERITA ACARA SERAH TERIMA PEKERJAAN (BAST)</h1>
                </td>
            </tr>
        </table>

        <p>
            Pada tanggal <strong>{{ $record->performed_at?->format('d/m/Y') }}</strong>,
            pekerjaan {{ str($record->type)->replace('_', ' ')->title() }} unit AC berikut telah dilaksanakan
            dan diserahterimakan kepada pihak gedung.
        </p>

        <h2>Data Gedung dan Unit AC</h2>
        <table class="details">
            <tr><th>Gedung</th><td>{{ $building->name }}</td></tr>
            <tr><th>Alamat</th><td>{{ $building->address ?: '—' }}</td></tr>
            <tr><th>Lokasi</th><td>{{ $airConditioner->room->floor->name }} — {{ $airConditioner->room->name }}</td></tr>
            <tr><th>Kode aset</th><td>{{ $airConditioner->asset_code }}</td></tr>
            <tr><th>Merk / kapasitas</th><td>{{ $airConditioner->brand }} / {{ $airConditioner->capacity_pk }} PK</td></tr>
            <tr><th>Jenis AC / refrigeran</th><td>{{ str($airConditioner->ac_type)->replace('_', ' ')->title() }} / {{ $airConditioner->refrigerant_type }}</td></tr>
            <tr><th>Teknisi</th><td>{{ $record->technician?->name ?? '—' }}</td></tr>
            <tr><th>Nomor invoice</th><td>{{ $invoice->invoice_number }}</td></tr>
            @if ($companySetting?->bank_name || $companySetting?->bank_account_number)
                <tr>
                    <th>Rekening perusahaan</th>
                    <td>{{ $companySetting->bank_name ?? '' }} {{ $companySetting->bank_account_number ? ' — '.$companySetting->bank_account_number : '' }} {{ $companySetting->bank_account_holder ? ' a.n. '.$companySetting->bank_account_holder : '' }}</td>
                </tr>
            @endif
        </table>

        <h2>Rincian Pekerjaan</h2>
        <table class="details">
            <tr><th>Keluhan / diagnosa</th><td>{{ $record->issue_description ?: '—' }}</td></tr>
            <tr><th>Tindakan perbaikan</th><td>{{ $record->action_taken ?: '—' }}</td></tr>
            <tr><th>Tekanan freon</th><td>{{ $record->pressure_psi !== null ? $record->pressure_psi.' PSI' : '—' }}</td></tr>
            <tr><th>Arus listrik</th><td>{{ $record->current_ampere !== null ? $record->current_ampere.' A' : '—' }}</td></tr>
            <tr><th>Suhu tiup / tegangan</th><td>{{ $record->blow_temp_celsius !== null ? $record->blow_temp_celsius.' °C' : '—' }} / {{ $record->voltage !== null ? $record->voltage.' V' : '—' }}</td></tr>
            <tr><th>Biaya pekerjaan</th><td>Rp {{ number_format((float) $record->total_cost, 0, ',', '.') }}</td></tr>
            <tr><th>Hasil serah terima</th><td>Pekerjaan telah selesai dan diterima oleh pihak gedung.</td></tr>
        </table>

        <h2>Dokumentasi</h2>
        <table class="photos">
            <tr>
                <td>
                    <strong>Foto sebelum</strong><br>
                    @if ($photo['before'])
                        <img src="{{ $photo['before'] }}" alt="Foto sebelum">
                    @else
                        <p class="muted">Tidak ada foto.</p>
                    @endif
                </td>
                <td>
                    <strong>Foto sesudah</strong><br>
                    @if ($photo['after'])
                        <img src="{{ $photo['after'] }}" alt="Foto sesudah">
                    @else
                        <p class="muted">Tidak ada foto.</p>
                    @endif
                </td>
            </tr>
        </table>

        <p>
            Demikian berita acara ini dibuat dengan sebenarnya untuk dipergunakan sebagaimana mestinya.
        </p>

        <table class="signatures">
            <tr>
                <td>
                    {{ $companySetting?->signatory_title ?: 'Perwakilan perusahaan' }},
                    <div class="signature-space"></div>
                    ({{ $companySetting?->signatory_name ?: ($record->technician?->name ?? '________________________') }})
                </td>
                <td>
                    Pihak / PIC Gedung,
                    <div class="signature-space"></div>
                    (________________________)
                </td>
            </tr>
        </table>

        @if (! $loop->last)
            <div class="page-break"></div>
        @endif
    @empty
        <table class="heading">
            <tr><td><h1>{{ $companyName }} — BERITA ACARA SERAH TERIMA PEKERJAAN (BAST)</h1></td></tr>
        </table>
        <p>Invoice {{ $invoice->invoice_number }} belum memiliki pekerjaan servis untuk dibuatkan BAST.</p>
    @endforelse
</body>
</html>
