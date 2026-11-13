<?php

namespace App\Filament\Pages;

use App\Models\CompanySetting;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Page;

class ManageCompanySetting extends Page
{
    protected static ?string $navigationGroup = 'Pengaturan';

    protected static ?string $navigationIcon = 'heroicon-o-building-office-2';

    protected static ?string $navigationLabel = 'Profil perusahaan';

    protected static ?string $title = 'Pengaturan Profil Perusahaan';

    protected static ?int $navigationSort = 1;

    protected static string $view = 'filament.pages.manage-company-setting';

    public ?array $data = [];

    public function mount(): void
    {
        $this->form->fill(CompanySetting::query()->first()?->attributesToArray() ?? [
            'company_name' => config('app.name'),
        ]);
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Identitas Perusahaan')
                    ->schema([
                        Forms\Components\TextInput::make('company_name')
                            ->label('Nama perusahaan')
                            ->required()
                            ->maxLength(255),
                        Forms\Components\Textarea::make('company_address')
                            ->label('Alamat')
                            ->rows(3)
                            ->columnSpanFull(),
                        Forms\Components\TextInput::make('company_phone')
                            ->label('Telepon')
                            ->tel()
                            ->maxLength(255),
                        Forms\Components\TextInput::make('company_email')
                            ->label('Email')
                            ->email()
                            ->maxLength(255),
                        Forms\Components\FileUpload::make('logo_path')
                            ->label('Logo perusahaan')
                            ->image()
                            ->acceptedFileTypes(['image/jpeg', 'image/png'])
                            ->disk('public')
                            ->directory('company')
                            ->imageEditor()
                            ->columnSpanFull(),
                    ])
                    ->columns(2),
                Forms\Components\Section::make('Informasi Rekening Bank')
                    ->schema([
                        Forms\Components\TextInput::make('bank_name')->label('Nama bank')->maxLength(255),
                        Forms\Components\TextInput::make('bank_account_number')->label('Nomor rekening')->maxLength(255),
                        Forms\Components\TextInput::make('bank_account_holder')->label('Nama pemilik rekening')->maxLength(255),
                    ])
                    ->columns(3),
                Forms\Components\Section::make('Penandatangan')
                    ->schema([
                        Forms\Components\TextInput::make('signatory_name')->label('Nama penandatangan')->maxLength(255),
                        Forms\Components\TextInput::make('signatory_title')->label('Jabatan')->maxLength(255),
                    ])
                    ->columns(2),
            ])
            ->statePath('data');
    }

    public function save(): void
    {
        $data = $this->form->getState();
        $setting = CompanySetting::query()->first() ?? new CompanySetting;
        $setting->fill($data);
        $setting->save();

        Notification::make()
            ->success()
            ->title('Profil perusahaan berhasil disimpan')
            ->send();
    }
}
