<?php

namespace Tests\Feature;

use App\Filament\Pages\Auth\Login;
use App\Filament\Resources\AirConditionerResource;
use App\Filament\Resources\AirConditionerResource\Pages\ListAirConditioners;
use App\Filament\Resources\InvoiceResource\Pages\ListInvoices;
use App\Filament\Resources\MaintenanceRecordResource\Pages\ListMaintenanceRecords;
use App\Filament\Resources\SparePartResource\Pages\ListSpareParts;
use App\Models\AirConditioner;
use App\Models\AssetMutation;
use App\Models\Building;
use App\Models\CompanySetting;
use App\Models\Floor;
use App\Models\Room;
use App\Models\User;
use Filament\Facades\Filament;
use Filament\Tables\Actions\ActionGroup;
use Filament\Tables\Enums\FiltersLayout;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Livewire\Livewire;
use PhpOffice\PhpSpreadsheet\IOFactory;
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

        $this->assertSame('Data AC', AirConditionerResource::getModelLabel());
        $this->assertSame('Data AC', AirConditionerResource::getPluralModelLabel());
        $this->assertSame('Data AC', AirConditionerResource::getNavigationLabel());
        $this->assertSame('Data AC', AirConditionerResource::getNavigationGroup());
        $this->assertSame('Sistem Manajemen AC', Filament::getPanel('admin')->getBrandName());
    }

    public function test_admin_login_uses_the_custom_animated_branding_and_keeps_filament_login_form(): void
    {
        auth()->logout();

        $this->get('/admin/login')
            ->assertOk()
            ->assertSee('Sistem Manajemen AC')
            ->assertSee('ac-login-page')
            ->assertSee('background-image: linear-gradient(135deg, #f8fafc 0%, #e2e8f0 50%, #dbeafe 100%)')
            ->assertSee('background: rgba(255, 255, 255, .85)')
            ->assertSee('backdrop-filter: blur(20px)')
            ->assertSee('background: radial-gradient(circle, rgba(186, 230, 253, .95)')
            ->assertSee('background: radial-gradient(circle, rgba(254, 215, 170, .92)')
            ->assertSee('ac-login-fade-up')
            ->assertSee('Memverifikasi akses...')
            ->assertSee('wire:submit="authenticate"', false)
            ->assertSee('wire:model="data.email"', false)
            ->assertSee('wire:model="data.password"', false)
            ->assertSee('wire:loading.flex', false);

        $this->assertSame('Sistem Manajemen AC', (new Login)->getHeading());
        $this->assertSame('Masuk', (new Login)->getTitle());
    }

    public function test_panel_uses_spa_global_animations_and_company_logo_favicon(): void
    {
        $this->actingAs(User::factory()->create());

        $panel = Filament::getPanel('admin');
        $this->assertTrue($panel->hasSpaMode());
        $this->assertTrue($panel->isSidebarCollapsibleOnDesktop());

        $this->get('/admin/air-conditioners')
            ->assertOk()
            ->assertSee('--livewire-progress-bar-color: #38bdf8')
            ->assertSee('ac-masterpiece-fade-up')
            ->assertSee('ac-masterpiece-pulse')
            ->assertSee('wire:navigate', false)
            ->assertSee('$store.sidebar.open()', false)
            ->assertSee('$store.sidebar.close()', false);

        CompanySetting::query()->create([
            'company_name' => 'PT Pakuan Karya Mandiri',
            'logo_path' => 'company/logo.png',
        ]);

        $this->get('/admin/air-conditioners')
            ->assertOk()
            ->assertSee(asset('storage/company/logo.png'));
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

    public function test_air_conditioner_row_actions_are_grouped_in_an_ellipsis_menu(): void
    {
        $this->actingAs(User::factory()->create());

        $airConditioner = $this->createAirConditioner();

        $component = Livewire::actingAs(auth()->user())
            ->test(ListAirConditioners::class);

        $actions = $component->instance()->getTable()->getActions();
        $this->assertCount(1, $actions);
        $this->assertInstanceOf(ActionGroup::class, $actions[0]);
        $this->assertSame('heroicon-m-ellipsis-vertical', $actions[0]->getIcon());
        $this->assertSame(
            ['view', 'printQrCode', 'mutateRoom', 'edit', 'delete'],
            array_map(fn ($action): string => $action->getName(), $actions[0]->getActions()),
        );

        $component
            ->assertTableActionExists('printQrCode', record: $airConditioner)
            ->assertTableActionExists('mutateRoom', record: $airConditioner)
            ->assertTableActionExists('view', record: $airConditioner)
            ->assertTableActionExists('edit', record: $airConditioner)
            ->assertTableActionExists('delete', record: $airConditioner);
    }

    public function test_resource_tables_expose_excel_export_and_camera_scan_actions(): void
    {
        $this->actingAs(User::factory()->create());

        Livewire::test(ListAirConditioners::class)
            ->assertActionExists('downloadTemplate')
            ->assertActionExists('importExcel')
            ->callAction('downloadTemplate')
            ->assertFileDownloaded(
                'template-data-ac.xlsx',
                contentType: 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            );

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

    public function test_resource_table_filters_use_modal_layout_and_global_css_prevents_clipping(): void
    {
        $this->actingAs(User::factory()->create());

        foreach ([
            ListAirConditioners::class,
            ListMaintenanceRecords::class,
            ListInvoices::class,
            ListSpareParts::class,
        ] as $page) {
            $table = Livewire::test($page)->instance()->getTable();
            $this->assertSame(FiltersLayout::Modal, $table->getFiltersLayout());
        }

        $this->get('/admin/air-conditioners')
            ->assertOk()
            ->assertSee('.fi-ta-ctn {', false)
            ->assertSee('overflow: visible !important;', false)
            ->assertSee('.fi-dropdown-panel,', false)
            ->assertSee('max-height: 75vh !important;', false)
            ->assertSee('overflow-y: auto !important;', false);
    }

    public function test_air_conditioner_template_download_is_a_formatted_xlsx_workbook(): void
    {
        $this->actingAs(User::factory()->create());

        $component = Livewire::test(ListAirConditioners::class)
            ->callAction('downloadTemplate')
            ->assertFileDownloaded(
                'template-data-ac.xlsx',
                contentType: 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            );
        $download = data_get($component->effects, 'download.content');
        $this->assertNotEmpty($download);

        $path = tempnam(sys_get_temp_dir(), 'ac-template-');

        try {
            file_put_contents($path, base64_decode($download, true));
            $workbook = IOFactory::load($path);
            $sheet = $workbook->getActiveSheet();

            $this->assertSame(
                ['gedung', 'lantai', 'ruangan', 'kode_aset', 'merk', 'tipe_ac', 'pk', 'jenis_refrigeran', 'tahun_produksi', 'kondisi'],
                $sheet->rangeToArray('A1:J1')[0],
            );
            $this->assertSame(
                ['GPS', '1', 'Lab Komputer', 'AC-GPS-001', 'Daikin', 'split_wall', '1', 'R32', '2022', 'baik'],
                array_map('strval', $sheet->rangeToArray('A2:J2')[0]),
            );
            $this->assertSame(
                ['GPS', '2', 'Ruang Dosen', 'AC-GPS-002', 'Panasonic', 'cassette', '2', 'R410A', '2021', 'baik'],
                array_map('strval', $sheet->rangeToArray('A3:J3')[0]),
            );
            $this->assertTrue($sheet->getStyle('A1:J1')->getFont()->getBold());

            $archive = new \ZipArchive;
            $this->assertSame(true, $archive->open($path));
            $worksheetXml = $archive->getFromName('xl/worksheets/sheet1.xml');
            $archive->close();
            $this->assertSame(10, substr_count($worksheetXml, 'bestFit="true"'));

            foreach (range('A', 'J') as $column) {
                $this->assertGreaterThan(0, $sheet->getColumnDimension($column)->getWidth());
            }

            $workbook->disconnectWorksheets();
        } finally {
            @unlink($path);
        }
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
