@php
    $airConditioner = $maintenanceRecord->airConditioner;
    $room = $airConditioner->room;
@endphp

<dl class="grid gap-4 sm:grid-cols-2">
    <div>
        <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Tanggal pengerjaan</dt>
        <dd class="mt-1 text-sm text-gray-950 dark:text-white">{{ $maintenanceRecord->performed_at?->format('d M Y') ?? '—' }}</dd>
    </div>
    <div>
        <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Unit AC</dt>
        <dd class="mt-1 text-sm text-gray-950 dark:text-white">{{ $airConditioner->asset_code }} — {{ $airConditioner->brand }} / {{ $airConditioner->capacity_pk }} PK</dd>
    </div>
    <div>
        <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Lokasi</dt>
        <dd class="mt-1 text-sm text-gray-950 dark:text-white">{{ $room->floor->building->name }} — {{ $room->floor->name }} — {{ $room->name }}</dd>
    </div>
    <div>
        <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Teknisi</dt>
        <dd class="mt-1 text-sm text-gray-950 dark:text-white">{{ $maintenanceRecord->technician?->name ?? '—' }}</dd>
    </div>
    <div>
        <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Status servis</dt>
        <dd class="mt-1 text-sm text-gray-950 dark:text-white">{{ $maintenanceRecord->status === 'selesai' ? 'Selesai' : 'Draft' }}</dd>
    </div>
    <div>
        <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Keluhan / diagnosa</dt>
        <dd class="mt-1 whitespace-pre-line text-sm text-gray-950 dark:text-white">{{ $maintenanceRecord->issue_description ?: '—' }}</dd>
    </div>
    <div class="sm:col-span-2">
        <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Tindakan perbaikan</dt>
        <dd class="mt-1 whitespace-pre-line text-sm text-gray-950 dark:text-white">{{ $maintenanceRecord->action_taken ?: '—' }}</dd>
    </div>
</dl>
