<?php

namespace Tests\Feature;

use App\Filament\Pages\MaintenanceSchedule;
use App\Models\AirConditioner;
use App\Models\Building;
use App\Models\Floor;
use App\Models\Room;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class MaintenanceScheduleTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('app.env', 'local');
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $this->actingAs(User::factory()->create());
    }

    public function test_schedule_page_summarizes_due_dates_and_exposes_quick_service_action(): void
    {
        $dueToday = $this->createAirConditioner('AC-DUE-TODAY', now()->subDays(90)->toDateString());
        $this->createAirConditioner('AC-OVERDUE', now()->subDays(100)->toDateString());
        $this->createAirConditioner('AC-NEVER-SERVICED', null);
        $this->createAirConditioner('AC-SAFE', now()->subDays(30)->toDateString());

        $this->get('/admin/maintenance-schedule')
            ->assertOk()
            ->assertSee('Jadwal Servis Berkala')
            ->assertSee('Jatuh Tempo Minggu Ini')
            ->assertSee('Jatuh Tempo Bulan Ini')
            ->assertSee('Lewat Jatuh Tempo')
            ->assertSee('AC-DUE-TODAY')
            ->assertSee('AC-OVERDUE')
            ->assertSee('Terlambat')
            ->assertSee('Aman');

        $component = Livewire::test(MaintenanceSchedule::class);
        $this->assertSame(1, $component->instance()->getDueThisWeekCount());
        $this->assertSame(1, $component->instance()->getDueThisMonthCount());
        $this->assertSame(2, $component->instance()->getOverdueCount());
        $component->assertTableActionExists('scheduleService', record: $dueToday);
    }

    private function createAirConditioner(string $assetCode, ?string $lastServicedAt): AirConditioner
    {
        $building = Building::query()->create(['name' => 'Gedung '.$assetCode]);
        $floor = Floor::query()->create([
            'building_id' => $building->id,
            'name' => 'Lantai 1',
            'floor_number' => 1,
        ]);
        $room = Room::query()->create([
            'floor_id' => $floor->id,
            'name' => 'Ruangan '.$assetCode,
        ]);

        return AirConditioner::query()->create([
            'room_id' => $room->id,
            'asset_code' => $assetCode,
            'brand' => 'Daikin',
            'capacity_pk' => '1',
            'last_serviced_at' => $lastServicedAt,
        ]);
    }
}
