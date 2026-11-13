<?php

namespace Tests\Feature;

use App\Filament\Resources\AirConditionerResource;
use App\Filament\Resources\AirConditionerResource\Pages\ListAirConditioners;
use App\Filament\Resources\InvoiceResource\Pages\ListInvoices;
use App\Filament\Resources\MaintenanceRecordResource\Pages\ListMaintenanceRecords;
use App\Models\AirConditioner;
use App\Models\AssetMutation;
use App\Models\Building;
use App\Models\Floor;
use App\Models\Room;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Livewire\Livewire;
use Tests\TestCase;

class FilamentResourcePagesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('app.env', 'local');
        Filament::setCurrentPanel(Filament::getPanel('admin'));
    }

    public function test_admin_can_open_master_data_and_air_conditioner_resource_pages(): void
    {
        config()->set('app.env', 'local');

        $this->actingAs(User::factory()->create());

        foreach ([
            '/admin/buildings',
            '/admin/buildings/create',
            '/admin/floors',
            '/admin/floors/create',
            '/admin/rooms',
            '/admin/rooms/create',
            '/admin/technicians',
            '/admin/technicians/create',
            '/admin/spare-parts',
            '/admin/spare-parts/create',
            '/admin/air-conditioners',
            '/admin/air-conditioners/create',
        ] as $uri) {
            $this->get($uri)->assertOk();
        }
    }

    public function test_air_conditioners_do_not_have_purchase_price_or_warranty_fields(): void
    {
        $this->actingAs(User::factory()->create());

        $this->assertFalse(Schema::hasColumn('air_conditioners', 'purchase_price'));
        $this->assertFalse(Schema::hasColumn('air_conditioners', 'warranty_until'));

        $this->get('/admin/air-conditioners/create')
            ->assertOk()
            ->assertDontSee('Harga beli')
            ->assertDontSee('Garansi sampai');
    }

    public function test_air_conditioner_qr_action_renders_a_printable_sticker(): void
    {
        $this->actingAs(User::factory()->create());

        $airConditioner = $this->createAirConditioner();

        Livewire::actingAs(auth()->user())
            ->test(ListAirConditioners::class)
            ->mountTableAction('printQrCode', $airConditioner)
            ->assertSee('Cetak stiker')
            ->assertSee($airConditioner->asset_code)
            ->assertSeeHtml('<img src="data:image/svg+xml;base64,')
            ->assertSeeHtml('target="_blank"')
            ->assertSee(route('admin.air-conditioners.print-qr', $airConditioner));

        $this->get(route('admin.air-conditioners.print-qr', $airConditioner))
            ->assertOk()
            ->assertSee('data:image/svg+xml;base64,')
            ->assertSee('window.print()')
            ->assertSee($airConditioner->asset_code)
            ->assertDontSee('fi-sidebar');

        $this->get(route('admin.air-conditioners.scan', ['assetCode' => $airConditioner->asset_code]))
            ->assertOk()
            ->assertJsonPath('url', AirConditionerResource::getUrl('view', ['record' => $airConditioner]));
    }

    public function test_resource_tables_expose_excel_export_and_camera_scan_actions(): void
    {
        $this->actingAs(User::factory()->create());

        Livewire::test(ListAirConditioners::class)
            ->assertTableActionExists('export')
            ->assertTableActionExists('scanQrCamera')
            ->mountTableAction('scanQrCamera')
            ->assertSee('air-conditioner-qr-reader')
            ->assertSee('html5-qrcode');

        Livewire::test(ListAirConditioners::class)
            ->callTableAction('export')
            ->assertHasNoTableActionErrors();

        Livewire::test(ListMaintenanceRecords::class)
            ->assertTableActionExists('export')
            ->callTableAction('export')
            ->assertHasNoTableActionErrors();

        Livewire::test(ListInvoices::class)
            ->assertTableActionExists('export')
            ->callTableAction('export')
            ->assertHasNoTableActionErrors();
    }

    public function test_room_mutation_updates_the_ac_and_creates_a_history_record(): void
    {
        $this->actingAs(User::factory()->create());

        $airConditioner = $this->createAirConditioner();
        $sourceRoomId = $airConditioner->room_id;
        $destinationRoom = Room::create([
            'floor_id' => $airConditioner->room->floor_id,
            'name' => 'Ruang tujuan',
        ]);

        Livewire::actingAs(auth()->user())
            ->test(ListAirConditioners::class)
            ->callTableAction('mutateRoom', $airConditioner, [
                'to_room_id' => $destinationRoom->id,
                'mutation_date' => '2026-11-13',
                'reason' => 'Penataan ulang',
                'transferred_by' => 'Admin',
            ])
            ->assertHasNoTableActionErrors();

        $this->assertDatabaseHas('air_conditioners', [
            'id' => $airConditioner->id,
            'room_id' => $destinationRoom->id,
        ]);

        $this->assertDatabaseHas('asset_mutations', [
            'air_conditioner_id' => $airConditioner->id,
            'from_room_id' => $sourceRoomId,
            'to_room_id' => $destinationRoom->id,
            'reason' => 'Penataan ulang',
            'transferred_by' => 'Admin',
        ]);

        $this->assertSame(
            '2026-11-13',
            AssetMutation::query()->where('air_conditioner_id', $airConditioner->id)->firstOrFail()->mutation_date->toDateString(),
        );
    }

    private function createAirConditioner(): AirConditioner
    {
        $building = Building::create(['name' => 'Gedung Utama']);
        $floor = Floor::create([
            'building_id' => $building->id,
            'name' => 'Lantai 1',
            'floor_number' => 1,
        ]);
        $room = Room::create([
            'floor_id' => $floor->id,
            'name' => 'Ruang Server',
        ]);

        return AirConditioner::create([
            'room_id' => $room->id,
            'asset_code' => 'AC-TEST-0001',
            'brand' => 'Test Brand',
            'ac_type' => 'split_wall',
            'capacity_pk' => '1',
        ]);
    }
}
