<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Stiker {{ $record->asset_code }}</title>
    <style>
        @page {
            size: 60mm 45mm;
            margin: 0;
        }

        * {
            box-sizing: border-box;
        }

        html,
        body {
            width: 60mm;
            min-height: 45mm;
            margin: 0;
            padding: 0;
            color: #111827;
            font-family: Arial, sans-serif;
        }

        .sticker {
            width: 60mm;
            min-height: 45mm;
            padding: 3mm;
            display: flex;
            align-items: center;
            gap: 3mm;
            border: 1px solid #d1d5db;
        }

        .qr {
            width: 34mm;
            height: 34mm;
            flex: 0 0 34mm;
        }

        .details {
            min-width: 0;
            overflow-wrap: anywhere;
        }

        .asset-code {
            margin-bottom: 2mm;
            font-size: 10pt;
            font-weight: 700;
        }

        .detail {
            margin-top: 1.5mm;
            font-size: 7pt;
        }

        @media screen {
            body {
                margin: 20px auto;
            }
        }

        @media print {
            .sticker {
                border: 0;
            }
        }
    </style>
</head>
<body onload="window.print()">
    <main class="sticker">
        <img class="qr" src="{{ $qrCodeString }}" alt="QR Code {{ $record->asset_code }}">
        <section class="details">
            <div class="asset-code">{{ $record->asset_code }}</div>
            <div class="detail">{{ $record->brand }} - {{ $record->capacity_pk }} PK</div>
            <div class="detail">{{ $record->room?->floor?->building?->name }} - {{ $record->room?->name }}</div>
        </section>
    </main>
</body>
</html>
