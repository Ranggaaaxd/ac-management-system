<?php

namespace Tests\Feature;

use App\Filament\Pages\ManageCompanySetting;
use App\Models\CompanySetting;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class CompanySettingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('app.env', 'local');
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $this->actingAs(User::factory()->create());
    }

    public function test_admin_can_save_the_company_profile(): void
    {
        $this->get('/admin/manage-company-setting')->assertOk();

        Livewire::test(ManageCompanySetting::class)
            ->fillForm([
                'company_name' => 'PT Sejuk Sentosa',
                'company_address' => 'Jl. Pendingin No. 8',
                'company_phone' => '021123456',
                'company_email' => 'info@example.test',
                'bank_name' => 'Bank Contoh',
                'bank_account_number' => '1234567890',
                'bank_account_holder' => 'PT Sejuk Sentosa',
                'signatory_name' => 'Budi Santoso',
                'signatory_title' => 'Direktur',
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('company_settings', [
            'company_name' => 'PT Sejuk Sentosa',
            'company_address' => 'Jl. Pendingin No. 8',
            'bank_account_number' => '1234567890',
            'signatory_name' => 'Budi Santoso',
        ]);

        $setting = CompanySetting::query()->firstOrFail();
        Livewire::test(ManageCompanySetting::class)
            ->fillForm(['company_name' => 'PT Sejuk Sentosa Baru'])
            ->call('save');

        $this->assertSame(1, CompanySetting::query()->count());
        $this->assertSame('PT Sejuk Sentosa Baru', $setting->fresh()->company_name);
    }

    public function test_company_settings_exposes_one_click_database_backup_action(): void
    {
        Livewire::test(ManageCompanySetting::class)
            ->assertActionExists('backupDatabase')
            ->assertSee('Backup Database (.sql)');
    }
}
