<?php

namespace Tests\Feature;

use App\Filament\Widgets\BuildingExpenseChartWidget;
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

class BuildingExpenseChartWidgetTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('app.env', 'local');
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $this->actingAs(User::factory()->create());
    }

    public function test_chart_groups_completed_service_costs_by_building_and_applies_dashboard_filters(): void
    {
        [$building, $airConditioner] = $this->createAirConditioner('Gedung A', 'AC-A-01');
        [, $otherAirConditioner] = $this->createAirConditioner('Gedung B', 'AC-B-01');

        $record = $airConditioner->maintenanceRecords()->create([
            'type' => 'perbaikan',
            'status' => 'selesai',
            'performed_at' => now()->toDateString(),
            'labor_cost' => 100000,
        ]);
        $sparePart = SparePart::query()->create([
            'name' => 'Kapasitor',
            'category' => 'kapasitor',
            'unit' => 'pcs',
            'stock' => 3,
            'minimum_stock' => 1,
        ]);
        $record->maintenanceSpareParts()->create([
            'spare_part_id' => $sparePart->id,
            'quantity' => 1,
            'unit_price' => 25000,
            'subtotal' => 25000,
        ]);

        $otherAirConditioner->maintenanceRecords()->create([
            'type' => 'perbaikan',
            'status' => 'selesai',
            'performed_at' => now()->subMonth()->toDateString(),
            'labor_cost' => 800000,
        ]);

        $widget = Livewire::test(BuildingExpenseChartWidget::class, [
            'filters' => [
                'building_id' => $building->id,
                'start_date' => now()->startOfMonth()->toDateString(),
                'end_date' => now()->endOfMonth()->toDateString(),
            ],
        ])->assertSee('Perbandingan Biaya Servis per Gedung')->instance();

        $dataMethod = new \ReflectionMethod($widget, 'getCachedData');
        $data = $dataMethod->invoke($widget);
        $this->assertSame('bar', (new \ReflectionMethod($widget, 'getType'))->invoke($widget));
        $this->assertSame(['Gedung A', 'Gedung B'], $data['labels']);
        $this->assertSame([125000.0, 0.0], $data['datasets'][0]['data']);

        $widget->filters = [
            'building_id' => null,
            'start_date' => now()->subMonth()->startOfMonth()->toDateString(),
            'end_date' => now()->subMonth()->endOfMonth()->toDateString(),
        ];
        $widget->updatedFilters();
        $filteredData = $dataMethod->invoke($widget);
        $this->assertSame(['Gedung A', 'Gedung B'], $filteredData['labels']);
        $this->assertSame([0.0, 800000.0], $filteredData['datasets'][0]['data']);
        $this->assertSame('#38bdf8', $filteredData['datasets'][0]['backgroundColor']);
        $this->assertSame('#0284c7', $filteredData['datasets'][0]['borderColor']);
        $this->assertSame(6, $filteredData['datasets'][0]['borderRadius']);

        $options = (new \ReflectionMethod($widget, 'getOptions'))->invoke($widget);
        $this->assertTrue($options['scales']['y']['beginAtZero']);
        $this->assertFalse($options['scales']['x']['ticks']['autoSkip']);
    }

    public function test_chart_shows_zero_value_buildings_when_selected_period_has_no_service_costs(): void
    {
        $this->createAirConditioner('Gedung A', 'AC-A-01');
        $this->createAirConditioner('Gedung B', 'AC-B-01');

        $component = Livewire::test(BuildingExpenseChartWidget::class, [
            'filters' => [
                'building_id' => null,
                'start_date' => now()->toDateString(),
                'end_date' => now()->toDateString(),
            ],
        ])->instance();

        $data = (new \ReflectionMethod($component, 'getCachedData'))->invoke($component);
        $this->assertSame(['Gedung A', 'Gedung B'], $data['labels']);
        $this->assertSame([0.0, 0.0], $data['datasets'][0]['data']);
    }

    /**
     * @return array{Building, AirConditioner}
     */
    private function createAirConditioner(string $buildingName, string $assetCode): array
    {
        $building = Building::query()->create(['name' => $buildingName]);
        $floor = Floor::query()->create([
            'building_id' => $building->id,
            'name' => 'Lantai 1',
            'floor_number' => 1,
        ]);
        $room = Room::query()->create([
            'floor_id' => $floor->id,
            'name' => 'Ruang 1',
        ]);
        $airConditioner = AirConditioner::query()->create([
            'room_id' => $room->id,
            'asset_code' => $assetCode,
            'brand' => 'Daikin',
            'capacity_pk' => '1',
        ]);

        return [$building, $airConditioner];
    }
}
