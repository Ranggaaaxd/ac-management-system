<?php

namespace App\Filament\Pages;

use App\Models\CompanySetting;
use App\Services\DatabaseBackupService;
use Filament\Actions\Action;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Response;
use Throwable;

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

    protected function getHeaderActions(): array
    {
        return [
            Action::make('backupDatabase')
                ->label('Backup Database (.sql)')
                ->icon('heroicon-m-arrow-down-tray')
                ->color('success')
                ->requiresConfirmation()
                ->action(function () {
                    $backup = fopen('php://temp/maxmemory:5242880', 'w+b');

                    if ($backup === false) {
                        throw new \RuntimeException('Stream sementara untuk backup tidak dapat dibuka.');
                    }

                    try {
                        app(DatabaseBackupService::class)->writeTo($backup);
                        rewind($backup);
                    } catch (Throwable $exception) {
                        fclose($backup);

                        throw $exception;
                    }

                    Notification::make()
                        ->success()
                        ->title('Backup database siap diunduh')
                        ->send();

                    return Response::streamDownload(function () use ($backup): void {
                        try {
                            if (fpassthru($backup) === false) {
                                throw new \RuntimeException('Pengiriman file backup gagal.');
                            }
                        } finally {
                            fclose($backup);
                        }
                    }, 'backup-ac-management-'.now()->format('Y-m-d_His').'.sql', [
                        'Content-Type' => 'application/sql; charset=UTF-8',
                    ]);
                }),
        ];
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
                            ->acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp'])
                            ->rules(['image', 'mimes:jpeg,png,webp'])
                            ->maxSize(2048)
                            ->disk('public')
                            ->directory('company')
                            ->preserveFilenames(false)
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
