<?php

namespace Tests\Feature;

use App\Filament\Pages\ActiveMaintenance;
use App\Filament\Resources\AirConditionerResource\Pages\ViewAirConditioner;
use App\Filament\Resources\AirConditionerResource\RelationManagers\MaintenanceRecordsRelationManager;
use App\Filament\Resources\MaintenanceRecordResource;
use App\Filament\Resources\MaintenanceRecordResource\Pages\CreateMaintenanceRecord;
use App\Filament\Resources\MaintenanceRecordResource\Pages\EditMaintenanceRecord;
use App\Filament\Resources\MaintenanceRecordResource\Pages\ListMaintenanceRecords;
use App\Filament\Resources\SparePartResource\Pages\ListSpareParts;
use App\Filament\Resources\SparePartResource\Pages\ViewSparePart;
use App\Filament\Resources\SparePartResource\RelationManagers\MaintenanceSparePartsRelationManager;
use App\Filament\Resources\SparePartResource\RelationManagers\SparePartStockInsRelationManager;
use App\Models\ActivityLog;
use App\Models\AirConditioner;
use App\Models\Building;
use App\Models\Floor;
use App\Models\MaintenanceRecord;
use App\Models\Room;
use App\Models\SparePart;
use App\Models\SparePartStockIn;
use App\Models\User;
use App\Services\MaintenanceInventory;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\TestCase;

class MaintenanceRecordResourceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('app.env', 'local');
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $this->actingAs(User::factory()->create());
    }

    public function test_maintenance_record_form_and_air_conditioner_medical_history_render(): void
    {
        $airConditioner = $this->createAirConditioner();

        $this->assertFalse(Schema::hasColumn('maintenance_records', 'is_warranty_claim'));

        $this->get('/admin/maintenance-records')->assertOk();
        $this->get('/admin/maintenance-records/create')
            ->assertOk()
            ->assertDontSee('Klaim Garansi')
            ->assertDontSee('is_warranty_claim');
        $this->get("/admin/air-conditioners/{$airConditioner->id}")
            ->assertOk()
            ->assertSee($airConditioner->asset_code);
    }

    public function test_air_conditioner_relation_manager_lists_maintenance_records_and_total_costs(): void
    {
        $airConditioner = $this->createAirConditioner();
        $record = $airConditioner->maintenanceRecords()->create([
            'type' => 'pengecekan',
            'performed_at' => '2026-11-13',
            'status' => 'selesai',
            'labor_cost' => 100000,
        ]);

        Livewire::test(MaintenanceRecordsRelationManager::class, [
            'ownerRecord' => $airConditioner,
            'pageClass' => ViewAirConditioner::class,
        ])
            ->assertCanSeeTableRecords([$record])
            ->assertSee('100,000');
    }

    public function test_spare_part_detail_lists_usage_history_and_opens_related_service_details(): void
    {
        $airConditioner = $this->createAirConditioner();
        $sparePart = SparePart::create([
            'name' => 'Freon R32',
            'category' => 'freon',
            'unit' => 'gram',
            'stock' => 1000,
            'minimum_stock' => 100,
        ]);
        $record = $airConditioner->maintenanceRecords()->create([
            'type' => 'perbaikan',
            'performed_at' => '2026-11-13',
            'status' => 'selesai',
            'issue_description' => 'Pendinginan kurang maksimal',
            'action_taken' => 'Tambah freon dan uji tekanan',
        ]);
        $record->maintenanceSpareParts()->create([
            'spare_part_id' => $sparePart->id,
            'quantity' => 10,
            'unit_price' => 500,
            'subtotal' => 5000,
        ]);

        $this->get("/admin/spare-parts/{$sparePart->id}")
            ->assertOk()
            ->assertSee($sparePart->name);

        Livewire::test(MaintenanceSparePartsRelationManager::class, [
            'ownerRecord' => $sparePart,
            'pageClass' => ViewSparePart::class,
        ])
            ->assertCanSeeTableRecords([$record->maintenanceSpareParts()->firstOrFail()])
            ->assertSee('10 gram')
            ->assertSee('Gedung Utama - Lantai 1 - Ruang Server')
            ->assertSee('Selesai')
            ->mountTableAction('viewMaintenanceRecord', $record->maintenanceSpareParts()->firstOrFail())
            ->assertSee('Lihat catatan servis')
            ->assertSee('Tambah freon dan uji tekanan');
    }

    public function test_spare_part_stock_in_action_records_receipt_increments_stock_and_logs_activity(): void
    {
        $sparePart = SparePart::create([
            'name' => 'Freon R32',
            'category' => 'freon',
            'unit' => 'kg',
            'stock' => 2,
            'minimum_stock' => 1,
        ]);

        Livewire::test(ListSpareParts::class)
            ->assertTableActionExists('addStock', record: $sparePart)
            ->callTableAction('addStock', $sparePart, [
                'quantity' => 2.5,
                'incoming_date' => '2026-11-13',
                'supplier' => 'Toko AC Sejahtera',
                'purchase_price' => 125000,
                'notes' => 'Nota 123',
            ])
            ->assertHasNoTableActionErrors();

        $stockIn = SparePartStockIn::query()->sole();

        $this->assertSame(450, (int) round((float) $sparePart->fresh()->stock * 100));
        $this->assertSame(250, (int) round((float) $stockIn->quantity * 100));
        $this->assertSame('2026-11-13', $stockIn->incoming_date->toDateString());
        $this->assertSame('Toko AC Sejahtera', $stockIn->supplier);
        $this->assertDatabaseHas('activity_logs', [
            'action' => 'UPDATE',
            'subject' => 'Sparepart',
            'description' => 'Stok Freon R32 ditambah 2,5 kg.',
        ]);

        Livewire::test(SparePartStockInsRelationManager::class, [
            'ownerRecord' => $sparePart,
            'pageClass' => ViewSparePart::class,
        ])
            ->assertCanSeeTableRecords([$stockIn])
            ->assertSee('Toko AC Sejahtera')
            ->assertSee('Nota 123');

        Livewire::test(ViewSparePart::class, ['record' => $sparePart->getKey()])
            ->assertActionExists('addStock');

        $this->assertSame(1, ActivityLog::query()->where('subject', 'Sparepart')->count());
    }

    public function test_service_working_list_and_history_are_separated_by_status(): void
    {
        $airConditioner = $this->createAirConditioner();
        $draft = $airConditioner->maintenanceRecords()->create([
            'type' => 'pengecekan',
            'performed_at' => '2026-11-13',
            'status' => 'draft',
        ]);
        $completed = $airConditioner->maintenanceRecords()->create([
            'type' => 'perbaikan',
            'performed_at' => '2026-11-12',
            'status' => 'selesai',
        ]);

        Livewire::test(ActiveMaintenance::class)
            ->assertCanSeeTableRecords([$draft])
            ->assertCanNotSeeTableRecords([$completed])
            ->assertActionExists('createMaintenance');

        Livewire::test(ListMaintenanceRecords::class)
            ->assertCanSeeTableRecords([$completed])
            ->assertCanNotSeeTableRecords([$draft]);

        $this->assertSame('Riwayat Servis', MaintenanceRecordResource::getNavigationLabel());
        $this->get('/admin/active-maintenance')->assertOk();
    }

    public function test_completed_maintenance_consumes_inventory_once_and_updates_service_history(): void
    {
        $airConditioner = $this->createAirConditioner();
        $sparePart = SparePart::create([
            'name' => 'Freon R32',
            'category' => 'freon',
            'unit' => 'kg',
            'stock' => 10,
            'minimum_stock' => 1,
        ]);

        Livewire::test(CreateMaintenanceRecord::class)
            ->fillForm([
                'air_conditioner_id' => $airConditioner->id,
                'type' => 'perbaikan',
                'performed_at' => '2026-11-13',
                'status' => 'selesai',
                'labor_cost' => 150000,
                'maintenanceSpareParts' => [
                    'item-1' => [
                        'spare_part_id' => $sparePart->id,
                        'quantity' => 1.5,
                        'unit_price' => 200000,
                    ],
                ],
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $record = MaintenanceRecord::query()->with('maintenanceSpareParts')->firstOrFail();

        $this->assertSame(850, (int) round((float) $sparePart->fresh()->stock * 100));
        $this->assertSame('2026-11-13', $airConditioner->fresh()->last_serviced_at->toDateString());
        $this->assertSame('baik', $airConditioner->fresh()->condition);
        $this->assertSame(450000.0, $record->total_cost);

        $inventory = app(MaintenanceInventory::class);
        $previous = $inventory->snapshot($record);
        $record->maintenanceSpareParts()->firstOrFail()->update(['quantity' => 2]);
        $inventory->reconcile($previous, $record->fresh());

        $this->assertSame(800, (int) round((float) $sparePart->fresh()->stock * 100));

        $previous = $inventory->snapshot($record->fresh());
        $record->update(['status' => 'draft']);
        $inventory->reconcile($previous, $record->fresh());

        $this->assertSame(1000, (int) round((float) $sparePart->fresh()->stock * 100));
        $this->assertNull($airConditioner->fresh()->last_serviced_at);
    }

    public function test_insufficient_stock_rolls_back_completion_and_does_not_change_stock(): void
    {
        $airConditioner = $this->createAirConditioner();
        $sparePart = SparePart::create([
            'name' => 'Kapasitor',
            'category' => 'kapasitor',
            'unit' => 'pcs',
            'stock' => 0.5,
            'minimum_stock' => 1,
        ]);
        $record = $airConditioner->maintenanceRecords()->create([
            'type' => 'pengecekan',
            'performed_at' => '2026-11-13',
            'status' => 'draft',
        ]);
        $record->maintenanceSpareParts()->create([
            'spare_part_id' => $sparePart->id,
            'quantity' => 1,
            'unit_price' => 10000,
            'subtotal' => 10000,
        ]);
        $record->update(['status' => 'selesai']);

        try {
            app(MaintenanceInventory::class)->reconcile(
                ['air_conditioner_id' => $airConditioner->id, 'status' => 'draft', 'items' => [$sparePart->id => 100]],
                $record->fresh(),
            );
            $this->fail('Completing maintenance with insufficient stock should fail.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('data.maintenanceSpareParts', $exception->errors());
        }

        $this->assertSame(50, (int) round((float) $sparePart->fresh()->stock * 100));
        $this->assertNull($airConditioner->fresh()->last_serviced_at);
    }

    public function test_filament_rejects_completion_when_stock_is_insufficient(): void
    {
        $airConditioner = $this->createAirConditioner();
        $sparePart = SparePart::create([
            'name' => 'Kapasitor',
            'category' => 'kapasitor',
            'unit' => 'pcs',
            'stock' => 0.5,
            'minimum_stock' => 1,
        ]);

        Livewire::test(CreateMaintenanceRecord::class)
            ->fillForm([
                'air_conditioner_id' => $airConditioner->id,
                'type' => 'perbaikan',
                'performed_at' => '2026-11-13',
                'status' => 'selesai',
                'maintenanceSpareParts' => [
                    'item-1' => [
                        'spare_part_id' => $sparePart->id,
                        'quantity' => 1,
                        'unit_price' => 10000,
                    ],
                ],
            ])
            ->call('create')
            ->assertHasFormErrors();

        $this->assertDatabaseCount('maintenance_records', 0);
        $this->assertSame(50, (int) round((float) $sparePart->fresh()->stock * 100));
        $this->assertNull($airConditioner->fresh()->last_serviced_at);
    }

    public function test_editing_a_completed_record_adjusts_only_the_quantity_difference(): void
    {
        $airConditioner = $this->createAirConditioner();
        $sparePart = SparePart::create([
            'name' => 'Pipa tembaga',
            'category' => 'pipa',
            'unit' => 'meter',
            'stock' => 10,
            'minimum_stock' => 1,
        ]);
        $record = $airConditioner->maintenanceRecords()->create([
            'type' => 'perbaikan',
            'performed_at' => '2026-11-13',
            'status' => 'selesai',
        ]);
        $item = $record->maintenanceSpareParts()->create([
            'spare_part_id' => $sparePart->id,
            'quantity' => 1,
            'unit_price' => 10000,
            'subtotal' => 10000,
        ]);
        app(MaintenanceInventory::class)->reconcile(null, $record);

        Livewire::test(EditMaintenanceRecord::class, ['record' => $record->getKey()])
            ->set("data.maintenanceSpareParts.record-{$item->id}.quantity", 2)
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame(800, (int) round((float) $sparePart->fresh()->stock * 100));
        $this->assertSame(2.0, (float) $item->fresh()->quantity);
    }

    public function test_deleting_a_completed_record_restores_used_stock(): void
    {
        $airConditioner = $this->createAirConditioner();
        $sparePart = SparePart::create([
            'name' => 'Freon R32',
            'category' => 'freon',
            'unit' => 'kg',
            'stock' => 10,
            'minimum_stock' => 1,
        ]);
        $record = $airConditioner->maintenanceRecords()->create([
            'type' => 'pengecekan',
            'performed_at' => '2026-11-13',
            'status' => 'selesai',
        ]);
        $record->maintenanceSpareParts()->create([
            'spare_part_id' => $sparePart->id,
            'quantity' => 1.25,
            'unit_price' => 10000,
            'subtotal' => 12500,
        ]);
        $inventory = app(MaintenanceInventory::class);
        $inventory->reconcile(null, $record);

        $inventory->delete($record);

        $this->assertSame(1000, (int) round((float) $sparePart->fresh()->stock * 100));
        $this->assertDatabaseMissing('maintenance_records', ['id' => $record->id]);
        $this->assertNull($airConditioner->fresh()->last_serviced_at);
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
