<?php

namespace Tests\Feature;

use App\Filament\Resources\AirConditionerResource;
use App\Filament\Resources\MaintenanceRecordResource;
use App\Filament\Resources\MaintenanceRecordResource\Pages\CreateMaintenanceRecord;
use App\Filament\Resources\SparePartResource;
use App\Filament\Widgets\AcConditionChartWidget;
use App\Filament\Widgets\AcNeedsAttentionWidget;
use App\Filament\Widgets\AcOverviewStatsWidget;
use App\Models\AirConditioner;
use App\Models\Building;
use App\Models\Floor;
use App\Models\Room;
use App\Models\SparePart;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class AcDashboardTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('app.env', 'local');
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $this->actingAs(User::factory()->create());
    }

    public function test_dashboard_renders_global_filters_and_widgets(): void
    {
        $response = $this->get('/admin')
            ->assertOk()
            ->assertSee('Gedung')
            ->assertSee('Dari tanggal')
            ->assertSee('Sampai tanggal')
            ->assertSee('Ringkasan Sistem AC')
            ->assertSee('Komposisi Kondisi AC')
            ->assertSee('Unit AC yang Membutuhkan Perhatian');

        $html = $response->getContent();
        $this->assertLessThan(strpos($html, 'Komposisi Kondisi AC'), strpos($html, 'Ringkasan Sistem AC'));
        $this->assertLessThan(strpos($html, 'Unit AC yang Membutuhkan Perhatian'), strpos($html, 'Komposisi Kondisi AC'));
    }

    public function test_stats_widget_counts_building_period_and_low_stock_filters(): void
    {
        [$building, $airConditioner] = $this->createAirConditioner('Gedung A', 'AC-A-01');
        $this->createAirConditioner('Gedung B', 'AC-B-01');

        $airConditioner->update([
            'condition' => 'rusak_ringan',
            'production_year' => now()->year - 5,
        ]);
        foreach ([
            ['type' => 'perbaikan', 'performed_at' => now()->startOfMonth()->toDateString()],
            ['type' => 'pemasangan_baru', 'performed_at' => now()->toDateString()],
            ['type' => 'pengecekan', 'performed_at' => now()->subMonths(2)->toDateString()],
        ] as $record) {
            $airConditioner->maintenanceRecords()->create($record + ['status' => 'selesai']);
        }

        SparePart::create([
            'name' => 'Kapasitor',
            'category' => 'kapasitor',
            'unit' => 'pcs',
            'stock' => 1,
            'minimum_stock' => 1,
        ]);

        $widget = Livewire::test(AcOverviewStatsWidget::class, [
            'filters' => [
                'building_id' => $building->id,
                'start_date' => now()->startOfMonth()->toDateString(),
                'end_date' => now()->endOfMonth()->toDateString(),
            ],
        ])
            ->assertSee('Total AC')
            ->assertSee('AC Rusak')
            ->assertSee('Total Perbaikan')
            ->assertSee('Total Pemasangan')
            ->assertSee('Total Pengecekan')
            ->assertSee('AC Usia Lama')
            ->assertSee('Rekomendasi Ganti Unit')
            ->assertSee('Stok Menipis')
            ->assertSee('Ringkasan Sistem AC');

        $getValues = function () use ($widget): array {
            $method = new \ReflectionMethod($widget->instance(), 'getCachedStats');

            return array_map(
                fn ($stat): int|string => $stat->getValue(),
                $method->invoke($widget->instance()),
            );
        };

        $this->assertSame([1, 1, 1, 1, 0, 1, 0, 1], $getValues());

        $getUrls = function () use ($widget): array {
            $method = new \ReflectionMethod($widget->instance(), 'getCachedStats');

            return array_map(
                fn ($stat): ?string => $stat->getUrl(),
                $method->invoke($widget->instance()),
            );
        };

        $this->assertSame([
            AirConditionerResource::getUrl('index'),
            AirConditionerResource::getUrl('index', [
                'tableFilters' => [
                    'condition' => ['values' => ['rusak_ringan', 'rusak_berat', 'perlu_servis']],
                ],
            ]),
            MaintenanceRecordResource::getUrl('index', [
                'tableFilters' => ['type' => ['value' => 'perbaikan']],
            ]),
            MaintenanceRecordResource::getUrl('index', [
                'tableFilters' => ['type' => ['value' => 'pemasangan_baru']],
            ]),
            MaintenanceRecordResource::getUrl('index', [
                'tableFilters' => ['type' => ['value' => 'pengecekan']],
            ]),
            AirConditionerResource::getUrl('index'),
            AirConditionerResource::getUrl('index'),
            SparePartResource::getUrl('index'),
        ], $getUrls());

        $component = $widget->instance();
        $component->filters = [
            'building_id' => $building->id,
            'start_date' => now()->subMonth()->startOfMonth()->toDateString(),
            'end_date' => now()->subMonth()->endOfMonth()->toDateString(),
        ];
        $component->updatedFilters();

        $method = new \ReflectionMethod($component, 'getCachedStats');
        $updatedValues = array_map(fn ($stat): int|string => $stat->getValue(), $method->invoke($component));

        $this->assertSame([1, 1, 0, 0, 0, 1, 0, 1], $updatedValues);

        $columnsMethod = new \ReflectionMethod($widget->instance(), 'getColumns');
        $this->assertSame(4, $columnsMethod->invoke($widget->instance()));
    }

    public function test_condition_chart_reflects_building_and_date_filters(): void
    {
        [$building, $airConditioner] = $this->createAirConditioner('Gedung A', 'AC-A-01');
        $airConditioner->update(['condition' => 'rusak_ringan']);
        $airConditioner->maintenanceRecords()->create([
            'type' => 'pengecekan',
            'performed_at' => now()->toDateString(),
            'status' => 'selesai',
        ]);
        [, $otherBuildingAc] = $this->createAirConditioner('Gedung B', 'AC-B-01');
        $otherBuildingAc->update(['condition' => 'baik']);
        $otherBuildingAc->maintenanceRecords()->create([
            'type' => 'pengecekan',
            'performed_at' => now()->toDateString(),
            'status' => 'selesai',
        ]);

        $component = Livewire::test(AcConditionChartWidget::class, [
            'filters' => [
                'building_id' => $building->id,
                'start_date' => now()->startOfMonth()->toDateString(),
                'end_date' => now()->endOfMonth()->toDateString(),
            ],
        ])->assertSee('Komposisi Kondisi AC')->instance();

        $method = new \ReflectionMethod($component, 'getCachedData');
        $this->assertSame([0, 1, 0, 0], $method->invoke($component)['datasets'][0]['data']);

        $component->filters = [
            'building_id' => $building->id,
            'start_date' => now()->subMonth()->startOfMonth()->toDateString(),
            'end_date' => now()->subMonth()->endOfMonth()->toDateString(),
        ];
        $component->updatedFilters();

        $method = new \ReflectionMethod($component, 'getCachedData');
        $this->assertSame([0, 0, 0, 0], $method->invoke($component)['datasets'][0]['data']);
    }

    public function test_needs_attention_widget_filters_by_building_and_shows_all_attention_reasons(): void
    {
        [$building, $damagedAc] = $this->createAirConditioner('Gedung A', 'AC-A-RUSAK');
        [, $staleAc] = $this->createAirConditioner('Gedung A', 'AC-A-LAMA');
        [, $repeatedRepairAc] = $this->createAirConditioner('Gedung A', 'AC-A-REPEAT');
        [, $healthyAc] = $this->createAirConditioner('Gedung A', 'AC-A-SEHAT');
        [, $otherBuildingAc] = $this->createAirConditioner('Gedung B', 'AC-B-RUSAK');

        $damagedAc->update(['condition' => 'rusak_berat', 'last_serviced_at' => now()->toDateString()]);
        $staleAc->update(['last_serviced_at' => now()->subDays(91)->toDateString()]);
        $repeatedRepairAc->update(['last_serviced_at' => now()->toDateString()]);
        $healthyAc->update(['last_serviced_at' => now()->toDateString()]);
        $otherBuildingAc->update(['condition' => 'rusak_ringan']);

        foreach (range(1, 3) as $index) {
            $repeatedRepairAc->maintenanceRecords()->create([
                'type' => 'perbaikan',
                'performed_at' => now()->subDays($index)->toDateString(),
                'status' => 'selesai',
            ]);
        }

        Livewire::test(AcNeedsAttentionWidget::class, [
            'filters' => ['building_id' => $building->id],
        ])
            ->assertCanSeeTableRecords([$damagedAc, $staleAc, $repeatedRepairAc])
            ->assertCanNotSeeTableRecords([$healthyAc, $otherBuildingAc])
            ->assertTableActionHasUrl(
                'createMaintenance',
                MaintenanceRecordResource::getUrl('create', ['air_conditioner_id' => $damagedAc->id]),
                $damagedAc,
            )
            ->assertSee('Rusak berat')
            ->assertSee('Servis > 90 hari')
            ->assertSee('Evaluasi ganti unit');

    }

    public function test_create_service_action_prefills_the_selected_air_conditioner(): void
    {
        [, $airConditioner] = $this->createAirConditioner('Gedung A', 'AC-A-01');

        Livewire::withQueryParams(['air_conditioner_id' => $airConditioner->id])
            ->test(CreateMaintenanceRecord::class)
            ->assertSet('data.air_conditioner_id', (string) $airConditioner->id);
    }

    /**
     * @return array{Building, AirConditioner}
     */
    private function createAirConditioner(string $buildingName, string $assetCode): array
    {
        $building = Building::query()->firstOrCreate(['name' => $buildingName]);
        $floor = Floor::create([
            'building_id' => $building->id,
            'name' => 'Lantai 1',
            'floor_number' => 1,
        ]);
        $room = Room::create([
            'floor_id' => $floor->id,
            'name' => 'Ruang Server',
        ]);

        $airConditioner = AirConditioner::create([
            'room_id' => $room->id,
            'asset_code' => $assetCode,
            'brand' => 'Test Brand',
            'ac_type' => 'split_wall',
            'capacity_pk' => '1',
        ]);

        return [$building, $airConditioner];
    }
}
