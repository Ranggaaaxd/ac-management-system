<x-filament-panels::page>
    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
        <x-filament::section>
            <div class="flex items-center justify-between gap-4">
                <div>
                    <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Jatuh Tempo Minggu Ini</p>
                    <p class="mt-2 text-3xl font-semibold text-primary-600">{{ number_format($this->getDueThisWeekCount()) }}</p>
                </div>
                <x-filament::icon icon="heroicon-o-calendar-days" class="h-8 w-8 text-primary-500" />
            </div>
        </x-filament::section>

        <x-filament::section>
            <div class="flex items-center justify-between gap-4">
                <div>
                    <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Jatuh Tempo Bulan Ini</p>
                    <p class="mt-2 text-3xl font-semibold text-warning-600">{{ number_format($this->getDueThisMonthCount()) }}</p>
                </div>
                <x-filament::icon icon="heroicon-o-wrench-screwdriver" class="h-8 w-8 text-warning-500" />
            </div>
        </x-filament::section>

        <x-filament::section>
            <div class="flex items-center justify-between gap-4">
                <div>
                    <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Lewat Jatuh Tempo</p>
                    <p class="mt-2 text-3xl font-semibold text-danger-600">{{ number_format($this->getOverdueCount()) }}</p>
                </div>
                <x-filament::icon icon="heroicon-o-exclamation-triangle" class="h-8 w-8 text-danger-500" />
            </div>
        </x-filament::section>
    </div>

    <x-filament::section heading="Daftar Jadwal Cuci / Servis">
        {{ $this->table }}
    </x-filament::section>
</x-filament-panels::page>
