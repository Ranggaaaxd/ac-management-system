<?php

namespace Tests\Feature;

use App\Filament\Resources\InvoiceResource\Pages\CreateInvoice;
use App\Filament\Resources\InvoiceResource\Pages\EditInvoice;
use App\Models\AirConditioner;
use App\Models\Building;
use App\Models\CompanySetting;
use App\Models\Floor;
use App\Models\Invoice;
use App\Models\MaintenanceRecord;
use App\Models\Room;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class InvoiceResourceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('app.env', 'local');
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $this->actingAs(User::factory()->create());
    }

    public function test_invoice_pages_and_pdf_documents_render(): void
    {
        $building = Building::create([
            'name' => 'Gedung Utama',
            'address' => 'Jl. Merdeka 1',
        ]);
        $maintenanceRecord = $this->createCompletedMaintenanceRecord($building);
        CompanySetting::create([
            'company_name' => 'PT Sejuk Sentosa',
            'company_address' => 'Jl. Pendingin No. 8',
            'bank_name' => 'Bank Contoh',
            'bank_account_number' => '1234567890',
            'bank_account_holder' => 'PT Sejuk Sentosa',
            'signatory_name' => 'Budi Santoso',
            'signatory_title' => 'Direktur',
        ]);
        $invoice = Invoice::create([
            'invoice_number' => 'INV/20261113/TEST',
            'building_id' => $building->id,
            'invoice_date' => '2026-11-13',
            'due_date' => '2026-11-27',
            'total_amount' => 100000,
            'payment_status' => 'unpaid',
        ]);
        $maintenanceRecord->update(['invoice_id' => $invoice->id]);

        $this->get('/admin/invoices')->assertOk();
        $this->get('/admin/invoices/create')->assertOk();
        $this->get("/admin/invoices/{$invoice->id}")->assertOk();
        $this->get("/admin/invoices/{$invoice->id}/edit")->assertOk();

        Filament::setCurrentPanel(null);

        $this->get("/admin/invoices/{$invoice->id}/pdf")
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf')
            ->assertHeader('content-disposition', 'attachment; filename=invoice-inv20261113test.pdf');

        $this->get("/admin/invoices/{$invoice->id}/bast")
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf')
            ->assertHeader('content-disposition', 'attachment; filename=bast-inv20261113test.pdf');
    }

    public function test_invoice_and_bast_templates_use_company_profile(): void
    {
        $building = Building::create(['name' => 'Gedung Utama']);
        $maintenanceRecord = $this->createCompletedMaintenanceRecord($building);
        $invoice = Invoice::create([
            'invoice_number' => 'INV/20261113/CO01',
            'building_id' => $building->id,
            'invoice_date' => '2026-11-13',
            'total_amount' => 100000,
        ]);
        $maintenanceRecord->update(['invoice_id' => $invoice->id]);
        $invoice->load([
            'building',
            'maintenanceRecords.airConditioner.room.floor.building',
            'maintenanceRecords.technician',
            'maintenanceRecords.maintenanceSpareParts.sparePart',
        ]);
        $companySetting = CompanySetting::create([
            'company_name' => 'PT Kop Surat AC',
            'company_address' => 'Jl. Udara Sejuk 5',
            'bank_name' => 'Bank Nusantara',
            'bank_account_number' => '99887766',
            'bank_account_holder' => 'PT Kop Surat AC',
            'signatory_name' => 'Siti Aminah',
            'signatory_title' => 'Manajer Operasional',
        ]);

        $invoiceHtml = view('pdf.invoice', [
            'invoice' => $invoice,
            'companySetting' => $companySetting,
            'companyLogo' => 'data:image/png;base64,ZmFrZQ==',
        ])->render();
        $bastHtml = view('pdf.bast', [
            'invoice' => $invoice,
            'companySetting' => $companySetting,
            'companyLogo' => 'data:image/png;base64,ZmFrZQ==',
            'photos' => [],
        ])->render();

        foreach ([$invoiceHtml, $bastHtml] as $html) {
            $this->assertStringContainsString('PT Kop Surat AC', $html);
            $this->assertStringContainsString('data:image/png;base64,ZmFrZQ==', $html);
            $this->assertStringContainsString('99887766', $html);
            $this->assertStringContainsString('Siti Aminah', $html);
        }
    }

    public function test_creating_an_invoice_links_only_selected_eligible_completed_work_and_calculates_total(): void
    {
        $building = Building::create(['name' => 'Gedung Barat']);
        $selected = $this->createCompletedMaintenanceRecord($building, 125000);
        $unselected = $this->createCompletedMaintenanceRecord($building, 50000);
        $draft = $this->createCompletedMaintenanceRecord($building, 100000, status: 'draft');

        Livewire::test(CreateInvoice::class)
            ->fillForm([
                'invoice_number' => 'INV/20261113/ABCD',
                'building_id' => $building->id,
                'invoice_date' => '2026-11-13',
                'due_date' => '2026-11-27',
                'payment_status' => 'unpaid',
                'maintenance_record_ids' => [(string) $selected->id],
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $invoice = Invoice::query()->where('invoice_number', 'INV/20261113/ABCD')->firstOrFail();

        $this->assertSame(125000.0, (float) $invoice->total_amount);
        $this->assertSame($invoice->id, $selected->fresh()->invoice_id);
        $this->assertNull($unselected->fresh()->invoice_id);
        $this->assertNull($draft->fresh()->invoice_id);
    }

    public function test_invoice_billing_prevents_claiming_work_already_attached_to_another_invoice(): void
    {
        $building = Building::create(['name' => 'Gedung Timur']);
        $record = $this->createCompletedMaintenanceRecord($building, 75000);
        $existingInvoice = Invoice::create([
            'invoice_number' => 'INV/20261113/EXST',
            'building_id' => $building->id,
            'invoice_date' => '2026-11-13',
            'total_amount' => 75000,
        ]);
        $record->update(['invoice_id' => $existingInvoice->id]);

        Livewire::test(CreateInvoice::class)
            ->fillForm([
                'invoice_number' => 'INV/20261113/NEWW',
                'building_id' => $building->id,
                'invoice_date' => '2026-11-13',
                'maintenance_record_ids' => [(string) $record->id],
            ])
            ->call('create')
            ->assertHasFormErrors(['maintenance_record_ids']);

        $this->assertDatabaseMissing('invoices', ['invoice_number' => 'INV/20261113/NEWW']);
        $this->assertSame($existingInvoice->id, $record->fresh()->invoice_id);
    }

    public function test_editing_an_invoice_preserves_selected_work_and_recalculates_its_total(): void
    {
        $building = Building::create(['name' => 'Gedung Selatan']);
        $record = $this->createCompletedMaintenanceRecord($building, 95000);
        $invoice = Invoice::create([
            'invoice_number' => 'INV/20261113/EDIT',
            'building_id' => $building->id,
            'invoice_date' => '2026-11-13',
            'total_amount' => 95000,
        ]);
        $record->update(['invoice_id' => $invoice->id]);

        Livewire::test(EditInvoice::class, ['record' => $invoice->id])
            ->assertFormSet(['maintenance_record_ids' => [(string) $record->id]])
            ->set('data.payment_status', 'paid')
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame($invoice->id, $record->fresh()->invoice_id);
        $this->assertSame(95000.0, (float) $invoice->fresh()->total_amount);
        $this->assertSame('paid', $invoice->fresh()->payment_status);
    }

    private function createCompletedMaintenanceRecord(
        Building $building,
        float $laborCost = 100000,
        string $status = 'selesai',
    ): MaintenanceRecord {
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
            'asset_code' => 'AC-'.str_pad((string) (AirConditioner::count() + 1), 4, '0', STR_PAD_LEFT),
            'brand' => 'Test Brand',
            'ac_type' => 'split_wall',
            'capacity_pk' => '1',
        ]);

        return $airConditioner->maintenanceRecords()->create([
            'type' => 'perbaikan',
            'performed_at' => '2026-11-13',
            'status' => $status,
            'labor_cost' => $laborCost,
        ]);
    }
}
