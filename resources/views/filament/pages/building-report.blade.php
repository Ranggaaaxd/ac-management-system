<x-filament-panels::page>
    <x-filament::section heading="Filter Laporan">
        {{ $this->form }}
    </x-filament::section>

    @php($building = $this->data['building_id'] ? \App\Models\Building::find($this->data['building_id']) : null)
    @php($summary = $this->reportSummary())

    @if (! $building)
        <x-filament::section>
            <p class="text-sm text-gray-600 dark:text-gray-300">Pilih gedung untuk melihat ringkasan dan laporan.</p>
        </x-filament::section>
    @else
        @php($unitRows = $this->airConditionerRows())
        @php($maintenanceRows = $this->maintenanceRecordRows())

        <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-5">
            @foreach ([
                ['Total Unit AC', number_format($summary['units']), 'heroicon-o-cube', 'primary'],
                ['Kondisi Baik', number_format($summary['good']), 'heroicon-o-check-circle', 'success'],
                ['AC Rusak', number_format($summary['damaged']), 'heroicon-o-exclamation-triangle', 'danger'],
                ['Frekuensi Servis', number_format($summary['services']), 'heroicon-o-wrench-screwdriver', 'warning'],
                ['Akumulasi Biaya', 'Rp '.number_format($summary['cost'], 0, ',', '.'), 'heroicon-o-banknotes', 'info'],
            ] as [$label, $value, $icon, $color])
                <x-filament::section>
                    <div class="flex items-center justify-between gap-3">
                        <div>
                            <p class="text-sm text-gray-500 dark:text-gray-400">{{ $label }}</p>
                            <p class="mt-2 text-2xl font-semibold text-{{ $color }}-600">{{ $value }}</p>
                        </div>
                        <x-filament::icon :icon="$icon" class="h-7 w-7 text-{{ $color }}-500" />
                    </div>
                </x-filament::section>
            @endforeach
        </div>

        <x-filament::section heading="Daftar Unit AC">
            <div class="overflow-x-auto">
                <table class="w-full divide-y divide-gray-200 text-sm dark:divide-white/10">
                    <thead>
                        <tr class="text-left text-gray-600 dark:text-gray-300">
                            <th class="px-3 py-2">Kode AC</th>
                            <th class="px-3 py-2">Lantai</th>
                            <th class="px-3 py-2">Ruangan</th>
                            <th class="px-3 py-2">Merk / PK</th>
                            <th class="px-3 py-2">Kondisi</th>
                            <th class="px-3 py-2">Terakhir Servis</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-white/5">
                        @forelse ($unitRows as $airConditioner)
                            <tr>
                                <td class="px-3 py-2 font-medium">{{ $airConditioner->asset_code }}</td>
                                <td class="px-3 py-2">{{ $airConditioner->room->floor->name }}</td>
                                <td class="px-3 py-2">{{ $airConditioner->room->name }}</td>
                                <td class="px-3 py-2">{{ $airConditioner->brand }} / {{ $airConditioner->capacity_pk }} PK</td>
                                <td class="px-3 py-2">{{ str_replace('_', ' ', ucfirst($airConditioner->condition)) }}</td>
                                <td class="px-3 py-2">{{ $airConditioner->last_serviced_at?->format('d M Y') ?? 'Belum pernah' }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="px-3 py-6 text-center text-gray-500">Belum ada unit AC di gedung ini.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="mt-4">{{ $unitRows->links() }}</div>
        </x-filament::section>

        <x-filament::section heading="Riwayat Pengerjaan Servis">
            <div class="overflow-x-auto">
                <table class="w-full divide-y divide-gray-200 text-sm dark:divide-white/10">
                    <thead>
                        <tr class="text-left text-gray-600 dark:text-gray-300">
                            <th class="px-3 py-2">Tanggal</th>
                            <th class="px-3 py-2">Unit AC</th>
                            <th class="px-3 py-2">Tindakan</th>
                            <th class="px-3 py-2">Teknisi</th>
                            <th class="px-3 py-2 text-right">Total Biaya</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-white/5">
                        @forelse ($maintenanceRows as $record)
                            <tr>
                                <td class="px-3 py-2">{{ $record->performed_at->format('d M Y') }}</td>
                                <td class="px-3 py-2 font-medium">{{ $record->airConditioner->asset_code }}</td>
                                <td class="px-3 py-2">{{ $record->action_taken ?: $record->issue_description ?: '—' }}</td>
                                <td class="px-3 py-2">{{ $record->technician?->name ?? '—' }}</td>
                                <td class="px-3 py-2 text-right">Rp {{ number_format((float) $record->total_cost, 0, ',', '.') }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="px-3 py-6 text-center text-gray-500">Tidak ada servis selesai pada periode ini.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="mt-4">{{ $maintenanceRows->links() }}</div>
        </x-filament::section>
    @endif
</x-filament-panels::page>
