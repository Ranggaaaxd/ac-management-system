<?php

namespace Tests\Feature;

use App\Filament\Resources\AirConditionerResource\Pages\ListAirConditioners;
use App\Models\AirConditioner;
use App\Models\AssetMutation;
use App\Models\Building;
use App\Models\Floor;
use App\Models\Room;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
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

    public function test_air_conditioner_qr_action_renders_a_printable_sticker(): void
    {
        $this->actingAs(User::factory()->create());

        $airConditioner = $this->createAirConditioner();

        Livewire::actingAs(auth()->user())
            ->test(ListAirConditioners::class)
            ->mountTableAction('printQrCode', $airConditioner)
            ->assertSee('Cetak stiker')
            ->assertSee($airConditioner->asset_code);
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
