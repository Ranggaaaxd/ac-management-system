<x-filament-widgets::widget>
    <div class="dashboard-quick-actions">
        <x-filament::section
            heading="Aksi Cepat"
            description="Akses langsung ke pekerjaan utama sistem."
        >
            <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 xl:grid-cols-4">
                @foreach ($actions as $action)
                    <x-filament::button
                        :href="$action['url']"
                        :icon="$action['icon']"
                        :color="$action['color']"
                        tag="a"
                        class="w-full justify-center"
                    >
                        {{ $action['label'] }}
                    </x-filament::button>
                @endforeach
            </div>
        </x-filament::section>
    </div>
</x-filament-widgets::widget>
