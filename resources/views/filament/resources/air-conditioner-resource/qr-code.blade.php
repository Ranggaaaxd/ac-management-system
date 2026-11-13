<div class="space-y-4 text-center">
    <div id="asset-sticker" class="inline-flex flex-col items-center gap-3 rounded-lg border border-gray-300 bg-white p-6 text-gray-950">
        <div class="h-48 w-48">{!! $svg !!}</div>
        <strong class="text-lg">{{ $record->asset_code }}</strong>
        <span>{{ $record->brand }} — {{ $record->capacity_pk }} PK</span>
    </div>

    <button
        type="button"
        onclick="window.print()"
        class="no-print inline-flex items-center rounded-lg bg-primary-600 px-4 py-2 text-sm font-semibold text-white"
    >
        Cetak stiker
    </button>
</div>

<style>
    #asset-sticker svg {
        width: 100%;
        height: 100%;
    }

    @media print {
        body * {
            visibility: hidden !important;
        }

        #asset-sticker,
        #asset-sticker * {
            visibility: visible !important;
        }

        #asset-sticker {
            position: fixed;
            inset: 0 auto auto 0;
            border: 0;
            padding: 20mm;
        }

        .no-print {
            display: none !important;
        }
    }
</style>
