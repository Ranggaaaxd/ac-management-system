<?php

namespace Tests\Feature;

use App\Filament\Resources\ActivityLogResource;
use App\Models\ActivityLog;
use App\Models\AirConditioner;
use App\Models\Building;
use App\Models\Floor;
use App\Models\Invoice;
use App\Models\Room;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ActivityAuditTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('app.env', 'local');
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $this->actingAs(User::factory()->create(['name' => 'Admin Audit']));
    }

    public function test_air_conditioner_invoice_and_service_changes_are_logged_with_user_context(): void
    {
        $airConditioner = $this->createAirConditioner();
        $airConditioner->update(['brand' => 'Panasonic']);
        $airConditioner->delete();

        $invoice = Invoice::query()->create([
            'invoice_number' => 'INV-AUDIT-001',
            'invoice_date' => now()->toDateString(),
            'payment_status' => 'unpaid',
        ]);
        $invoice->update(['payment_status' => 'paid']);

        $serviceAc = $this->createAirConditioner('AC-SERVICE-01');
        $service = $serviceAc->maintenanceRecords()->create([
            'type' => 'perbaikan',
            'performed_at' => now()->toDateString(),
            'status' => 'draft',
        ]);
        $service->update(['status' => 'selesai']);

        $this->assertDatabaseHas('activity_logs', [
            'action' => 'CREATE',
            'subject' => 'Data AC',
            'user_name' => 'Admin Audit',
        ]);
        $this->assertDatabaseHas('activity_logs', [
            'action' => 'UPDATE',
            'subject' => 'Data AC',
        ]);
        $this->assertDatabaseHas('activity_logs', [
            'action' => 'DELETE',
            'subject' => 'Data AC',
        ]);
        $this->assertDatabaseHas('activity_logs', [
            'action' => 'STATUS_CHANGE',
            'subject' => 'Invoice',
            'description' => 'Invoice INV-AUDIT-001: status pembayaran unpaid menjadi paid.',
        ]);
        $this->assertDatabaseHas('activity_logs', [
            'action' => 'STATUS_CHANGE',
            'subject' => 'Servis',
            'description' => 'Status servis AC-SERVICE-01 berubah menjadi selesai.',
        ]);
    }

    public function test_activity_log_resource_is_read_only_and_lists_the_audit_trail(): void
    {
        ActivityLog::query()->create([
            'user_name' => 'Admin Audit',
            'action' => 'STATUS_CHANGE',
            'subject' => 'Invoice',
            'description' => 'Invoice status changed.',
            'ip_address' => '127.0.0.1',
        ]);

        $this->get('/admin/activity-logs')
            ->assertOk()
            ->assertSee('Riwayat aktivitas')
            ->assertSee('Admin Audit')
            ->assertSee('STATUS_CHANGE')
            ->assertSee('Invoice status changed.')
            ->assertSee('127.0.0.1')
            ->assertDontSee('Create Riwayat aktivitas');

        $this->assertFalse(ActivityLogResource::canCreate());
    }

    private function createAirConditioner(string $assetCode = 'AC-AUDIT-01'): AirConditioner
    {
        $building = Building::query()->create(['name' => 'Gedung Audit']);
        $floor = Floor::query()->create([
            'building_id' => $building->id,
            'name' => 'Lantai 1',
            'floor_number' => 1,
        ]);
        $room = Room::query()->create([
            'floor_id' => $floor->id,
            'name' => 'Ruang Audit',
        ]);

        return AirConditioner::query()->create([
            'room_id' => $room->id,
            'asset_code' => $assetCode,
            'brand' => 'Daikin',
            'capacity_pk' => '1',
        ]);
    }
}
