<?php

namespace App\Filament\Resources;

use App\Filament\Resources\MaintenanceRecordResource\Pages;
use App\Models\AirConditioner;
use App\Models\MaintenanceRecord;
use App\Models\SparePart;
use App\Models\Technician;
use App\Services\InvoiceBilling;
use App\Services\MaintenanceInventory;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Forms\Set;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Enums\FiltersLayout;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use pxlrbt\FilamentExcel\Actions\Tables\ExportAction;

class MaintenanceRecordResource extends Resource
{
    protected static ?string $model = MaintenanceRecord::class;

    protected static ?string $navigationGroup = 'Servis AC';

    protected static ?string $navigationIcon = 'heroicon-o-clipboard-document-check';

    protected static ?string $navigationLabel = 'Riwayat Servis';

    protected static ?string $modelLabel = 'Riwayat servis';

    protected static ?string $pluralModelLabel = 'Riwayat servis';

    protected static ?int $navigationSort = 2;

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('Informasi Utama')
                ->schema([
                    Forms\Components\Select::make('air_conditioner_id')
                        ->label('Unit AC')
                        ->relationship(
                            name: 'airConditioner',
                            titleAttribute: 'asset_code',
                            modifyQueryUsing: fn (Builder $query): Builder => $query->with(['room.floor.building']),
                        )
                        ->getOptionLabelFromRecordUsing(
                            fn (AirConditioner $record): string => "[{$record->asset_code}] {$record->brand} - {$record->room->name} ({$record->room->floor->building->name})",
                        )
                        ->searchable(['asset_code', 'brand'])
                        ->preload()
                        ->required(),
                    Forms\Components\Select::make('technician_id')
                        ->label('Teknisi')
                        ->options(fn (): array => Technician::query()
                            ->where('is_active', true)
                            ->orderBy('name')
                            ->pluck('name', 'id')
                            ->all())
                        ->searchable()
                        ->nullable(),
                    Forms\Components\Select::make('type')
                        ->label('Tipe pengerjaan')
                        ->options(self::typeOptions())
                        ->required(),
                    Forms\Components\DatePicker::make('performed_at')
                        ->label('Tanggal pengerjaan')
                        ->default(now())
                        ->required(),
                    Forms\Components\Select::make('status')
                        ->label('Status')
                        ->options([
                            'draft' => 'Draft',
                            'selesai' => 'Selesai',
                        ])
                        ->disabled(fn (?MaintenanceRecord $record): bool => filled($record?->invoice_id))
                        ->dehydrated()
                        ->default('draft')
                        ->required(),
                ])
                ->columns(2),
            Forms\Components\Section::make('Keluhan & Tindakan')
                ->schema([
                    Forms\Components\Textarea::make('issue_description')
                        ->label('Keluhan / Diagnosa')
                        ->rows(4),
                    Forms\Components\Textarea::make('action_taken')
                        ->label('Tindakan Perbaikan')
                        ->rows(4),
                ])
                ->columns(2),
            Forms\Components\Section::make('Pengukuran Teknis HVAC')
                ->description('Bagian ini opsional.')
                ->schema([
                    Forms\Components\TextInput::make('pressure_psi')
                        ->label('Tekanan Freon (PSI)')
                        ->numeric()
                        ->suffix('PSI'),
                    Forms\Components\TextInput::make('current_ampere')
                        ->label('Arus')
                        ->numeric()
                        ->suffix('A'),
                    Forms\Components\TextInput::make('blow_temp_celsius')
                        ->label('Suhu tiup')
                        ->numeric()
                        ->suffix('°C'),
                    Forms\Components\TextInput::make('voltage')
                        ->label('Tegangan')
                        ->numeric()
                        ->suffix('V'),
                ])
                ->columns(4)
                ->collapsible()
                ->collapsed(),
            Forms\Components\Section::make('Dokumentasi Foto')
                ->schema([
                    Forms\Components\FileUpload::make('before_photo')
                        ->label('Foto sebelum')
                        ->image()
                        ->acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp'])
                        ->rules(['image', 'mimes:jpeg,png,webp'])
                        ->maxSize(2048)
                        ->disk('public')
                        ->directory('maintenance/before')
                        ->preserveFilenames(false),
                    Forms\Components\FileUpload::make('after_photo')
                        ->label('Foto sesudah')
                        ->image()
                        ->acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp'])
                        ->rules(['image', 'mimes:jpeg,png,webp'])
                        ->maxSize(2048)
                        ->disk('public')
                        ->directory('maintenance/after')
                        ->preserveFilenames(false),
                ])
                ->columns(2),
            Forms\Components\Section::make('Biaya Jasa')
                ->schema([
                    Forms\Components\TextInput::make('labor_cost')
                        ->label('Biaya jasa')
                        ->numeric()
                        ->minValue(0)
                        ->prefix('Rp')
                        ->default(0)
                        ->dehydrated(),
                ])
                ->columns(1),
            Forms\Components\Section::make('Pemakaian Sparepart')
                ->schema([
                    Forms\Components\Repeater::make('maintenanceSpareParts')
                        ->label('Sparepart yang digunakan')
                        ->relationship()
                        ->schema([
                            Forms\Components\Select::make('spare_part_id')
                                ->label('Sparepart')
                                ->options(fn (): array => SparePart::query()->orderBy('name')->pluck('name', 'id')->all())
                                ->searchable()
                                ->required(),
                            Forms\Components\TextInput::make('quantity')
                                ->label('Jumlah')
                                ->numeric()
                                ->minValue(0.01)
                                ->default(1)
                                ->required()
                                ->live(debounce: 500)
                                ->afterStateUpdated(fn (Get $get, Set $set): mixed => self::updateSubtotal($get, $set)),
                            Forms\Components\TextInput::make('unit_price')
                                ->label('Harga satuan')
                                ->numeric()
                                ->minValue(0)
                                ->prefix('Rp')
                                ->default(0)
                                ->required()
                                ->live(debounce: 500)
                                ->afterStateUpdated(fn (Get $get, Set $set): mixed => self::updateSubtotal($get, $set)),
                            Forms\Components\TextInput::make('subtotal')
                                ->label('Subtotal')
                                ->numeric()
                                ->prefix('Rp')
                                ->disabled()
                                ->dehydrated(false),
                        ])
                        ->columns(4)
                        ->defaultItems(0)
                        ->addActionLabel('Tambah sparepart')
                        ->mutateRelationshipDataBeforeCreateUsing(
                            fn (array $data): array => self::calculateSubtotal($data),
                        )
                        ->mutateRelationshipDataBeforeSaveUsing(
                            fn (array $data): array => self::calculateSubtotal($data),
                        ),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query
                ->where('status', 'selesai')
                ->with([
                    'airConditioner.room.floor.building',
                    'technician',
                    'maintenanceSpareParts',
                ]))
            ->columns([
                Tables\Columns\TextColumn::make('performed_at')->label('Tanggal')->date()->sortable(),
                Tables\Columns\TextColumn::make('airConditioner.asset_code')
                    ->label('Unit AC')
                    ->formatStateUsing(fn (?string $state, MaintenanceRecord $record): string => sprintf(
                        '%s - %s',
                        $state,
                        $record->airConditioner->room->name,
                    ))
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('airConditioner.room.floor.building.name')
                    ->label('Lokasi')
                    ->formatStateUsing(fn (?string $state, MaintenanceRecord $record): string => implode(' - ', [
                        $state ?? '',
                        $record->airConditioner->room->floor->name,
                        $record->airConditioner->room->name,
                    ])),
                Tables\Columns\TextColumn::make('type')
                    ->label('Pengerjaan')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => self::typeOptions()[$state] ?? $state)
                    ->color('info')
                    ->sortable(),
                Tables\Columns\TextColumn::make('technician.name')->label('Teknisi')->default('—')->searchable(),
                Tables\Columns\TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => $state === 'selesai' ? 'Selesai' : 'Draft')
                    ->color(fn (string $state): string => $state === 'selesai' ? 'success' : 'gray')
                    ->sortable(),
                Tables\Columns\TextColumn::make('total_cost')
                    ->label('Total biaya')
                    ->state(fn (MaintenanceRecord $record): float => (float) $record->total_cost)
                    ->money('IDR'),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('type')
                    ->label('Tipe pengerjaan')
                    ->options(self::typeOptions()),
                Tables\Filters\SelectFilter::make('technician_id')
                    ->label('Teknisi')
                    ->relationship('technician', 'name')
                    ->searchable()
                    ->preload(),
                Tables\Filters\Filter::make('performed_at')
                    ->label('Rentang tanggal')
                    ->form([
                        Forms\Components\DatePicker::make('from')->label('Dari'),
                        Forms\Components\DatePicker::make('until')->label('Sampai'),
                    ])
                    ->query(fn (Builder $query, array $data): Builder => $query
                        ->when($data['from'] ?? null, fn (Builder $query, string $date): Builder => $query->whereDate('performed_at', '>=', $date))
                        ->when($data['until'] ?? null, fn (Builder $query, string $date): Builder => $query->whereDate('performed_at', '<=', $date))),
            ], layout: FiltersLayout::Modal)
            ->headerActions([
                ExportAction::make()->label('Export Excel'),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make()
                    ->action(function (MaintenanceRecord $record): void {
                        $invoiceId = $record->invoice_id;
                        app(MaintenanceInventory::class)->delete($record);

                        if ($invoiceId) {
                            app(InvoiceBilling::class)->refresh($invoiceId);
                        }
                    }),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListMaintenanceRecords::route('/'),
            'create' => Pages\CreateMaintenanceRecord::route('/create'),
            'edit' => Pages\EditMaintenanceRecord::route('/{record}/edit'),
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function typeOptions(): array
    {
        return [
            'pemasangan_baru' => 'Pemasangan baru',
            'pengecekan' => 'Pengecekan',
            'perawatan_berkala' => 'Perawatan berkala',
            'perbaikan' => 'Perbaikan',
        ];
    }

    private static function updateSubtotal(Get $get, Set $set): void
    {
        $set('subtotal', round((float) $get('quantity') * (float) $get('unit_price'), 2));
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private static function calculateSubtotal(array $data): array
    {
        $data['subtotal'] = round((float) ($data['quantity'] ?? 0) * (float) ($data['unit_price'] ?? 0), 2);

        return $data;
    }
}
