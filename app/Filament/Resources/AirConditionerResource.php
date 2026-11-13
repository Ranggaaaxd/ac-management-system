<?php

namespace App\Filament\Resources;

use App\Filament\Resources\AirConditionerResource\Pages;
use App\Filament\Resources\AirConditionerResource\RelationManagers\MaintenanceRecordsRelationManager;
use App\Models\AirConditioner;
use App\Models\Building;
use App\Models\Floor;
use App\Models\Room;
use App\Services\AirConditionerQrCode;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Forms\Set;
use Filament\Infolists\Components\Section as InfolistSection;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Infolist;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Actions\Action;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Str;
use pxlrbt\FilamentExcel\Actions\Tables\ExportAction;

class AirConditionerResource extends Resource
{
    protected static ?string $model = AirConditioner::class;

    protected static ?string $navigationGroup = 'Aset AC';

    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';

    protected static ?string $modelLabel = 'Unit AC';

    protected static ?string $pluralModelLabel = 'Aset AC';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Lokasi')
                    ->schema([
                        Forms\Components\Select::make('building_id')
                            ->label('Gedung')
                            ->options(fn (): array => Building::query()->orderBy('name')->pluck('name', 'id')->all())
                            ->searchable()
                            ->live()
                            ->dehydrated(false)
                            ->afterStateHydrated(function (Forms\Components\Select $component, ?AirConditioner $record): void {
                                if ($record) {
                                    $component->state($record->room->floor->building_id);
                                }
                            })
                            ->afterStateUpdated(function (Set $set): void {
                                $set('floor_id', null);
                                $set('room_id', null);
                            })
                            ->required(),
                        Forms\Components\Select::make('floor_id')
                            ->label('Lantai')
                            ->options(fn (Get $get): array => $get('building_id')
                                ? Floor::query()
                                    ->where('building_id', $get('building_id'))
                                    ->orderBy('floor_number')
                                    ->pluck('name', 'id')
                                    ->all()
                                : [])
                            ->searchable()
                            ->live()
                            ->dehydrated(false)
                            ->afterStateHydrated(function (Forms\Components\Select $component, ?AirConditioner $record): void {
                                if ($record) {
                                    $component->state($record->room->floor_id);
                                }
                            })
                            ->afterStateUpdated(fn (Set $set): mixed => $set('room_id', null))
                            ->required(),
                        Forms\Components\Select::make('room_id')
                            ->label('Ruangan')
                            ->options(fn (Get $get): array => $get('floor_id')
                                ? Room::query()
                                    ->where('floor_id', $get('floor_id'))
                                    ->orderBy('name')
                                    ->pluck('name', 'id')
                                    ->all()
                                : [])
                            ->searchable()
                            ->required(),
                    ])
                    ->columns(3),
                Forms\Components\Section::make('Spesifikasi AC')
                    ->schema([
                        Forms\Components\TextInput::make('asset_code')
                            ->label('Kode aset')
                            ->default(fn (): string => 'AC-'.Str::upper(Str::random(8)))
                            ->unique(ignoreRecord: true)
                            ->maxLength(255),
                        Forms\Components\TextInput::make('brand')
                            ->label('Merk')
                            ->required()
                            ->maxLength(255),
                        Forms\Components\Select::make('ac_type')
                            ->label('Tipe AC')
                            ->options(self::typeOptions())
                            ->default('split_wall')
                            ->required(),
                        Forms\Components\TextInput::make('capacity_pk')
                            ->label('Kapasitas (PK)')
                            ->placeholder('0.5, 1, 2')
                            ->required()
                            ->maxLength(255),
                        Forms\Components\TextInput::make('refrigerant_type')
                            ->label('Jenis refrigeran')
                            ->default('R32')
                            ->required()
                            ->maxLength(255),
                        Forms\Components\TextInput::make('production_year')
                            ->label('Tahun produksi')
                            ->numeric()
                            ->integer()
                            ->minValue(1900)
                            ->maxValue((int) date('Y') + 1),
                        Forms\Components\Select::make('condition')
                            ->label('Kondisi')
                            ->options(self::conditionOptions())
                            ->default('baik')
                            ->required(),
                        Forms\Components\Textarea::make('notes')
                            ->label('Catatan')
                            ->columnSpanFull(),
                    ])
                    ->columns(3),
            ]);
    }

    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist->schema([
            InfolistSection::make('Lokasi')
                ->schema([
                    TextEntry::make('room.floor.building.name')->label('Gedung'),
                    TextEntry::make('room.floor.name')->label('Lantai'),
                    TextEntry::make('room.name')->label('Ruangan'),
                ])
                ->columns(3),
            InfolistSection::make('Spesifikasi AC')
                ->schema([
                    TextEntry::make('asset_code')->label('Kode aset'),
                    TextEntry::make('brand')->label('Merk'),
                    TextEntry::make('ac_type')->label('Tipe AC'),
                    TextEntry::make('capacity_pk')->label('Kapasitas')->suffix(' PK'),
                    TextEntry::make('refrigerant_type')->label('Jenis refrigeran'),
                    TextEntry::make('production_year')->label('Tahun produksi'),
                    TextEntry::make('condition')->label('Kondisi')->badge(),
                    TextEntry::make('last_serviced_at')->label('Servis terakhir')->date()->placeholder('Belum pernah'),
                    TextEntry::make('notes')->label('Catatan')->columnSpanFull(),
                ])
                ->columns(3),
        ]);
    }

    public static function getRelations(): array
    {
        return [
            MaintenanceRecordsRelationManager::class,
        ];
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with(['room.floor.building']))
            ->columns([
                Tables\Columns\TextColumn::make('asset_code')->label('Kode aset')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('brand')->label('Merk')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('ac_type')
                    ->label('Tipe')
                    ->formatStateUsing(fn (string $state): string => self::typeOptions()[$state] ?? $state)
                    ->sortable(),
                Tables\Columns\TextColumn::make('capacity_pk')->label('PK')->suffix(' PK')->sortable(),
                Tables\Columns\TextColumn::make('room.name')
                    ->label('Lokasi')
                    ->formatStateUsing(fn (?string $state, AirConditioner $record): string => implode(' - ', [
                        $record->room->floor->building->name,
                        $record->room->floor->name,
                        $state,
                    ])),
                Tables\Columns\TextColumn::make('condition')
                    ->label('Kondisi')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => self::conditionOptions()[$state] ?? $state)
                    ->color(fn (string $state): string => match ($state) {
                        'baik' => 'success',
                        'rusak_ringan' => 'warning',
                        'rusak_berat' => 'danger',
                        'perlu_servis' => 'info',
                        default => 'gray',
                    })
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('building')
                    ->label('Gedung')
                    ->options(fn (): array => Building::query()->orderBy('name')->pluck('name', 'id')->all())
                    ->query(fn (Builder $query, array $data): Builder => $query->when(
                        $data['value'] ?? null,
                        fn (Builder $query, string $buildingId): Builder => $query->whereHas(
                            'room.floor',
                            fn (Builder $query): Builder => $query->where('building_id', $buildingId),
                        ),
                    )),
                Tables\Filters\SelectFilter::make('room_id')
                    ->label('Ruangan')
                    ->options(fn (): array => Room::query()
                        ->with('floor.building')
                        ->orderBy('name')
                        ->get()
                        ->mapWithKeys(fn (Room $room): array => [
                            $room->id => "{$room->floor->building->name} - {$room->floor->name} - {$room->name}",
                        ])
                        ->all()),
                Tables\Filters\SelectFilter::make('condition')
                    ->label('Kondisi')
                    ->multiple()
                    ->options(self::conditionOptions()),
            ])
            ->headerActions([
                ExportAction::make()->label('Export Excel'),
                Action::make('scanQrCamera')
                    ->label('Scan QR Kamera')
                    ->icon('heroicon-m-qr-code')
                    ->color('primary')
                    ->modalHeading('Scan QR Code Unit AC')
                    ->modalWidth('2xl')
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Tutup')
                    ->modalContent(fn () => view('filament.resources.air-conditioner-resource.qr-scanner')),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Action::make('printQrCode')
                    ->label('Cetak QR Code')
                    ->icon('heroicon-o-qr-code')
                    ->modalHeading(fn (AirConditioner $record): string => "QR Code {$record->asset_code}")
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Tutup')
                    ->modalContent(function (AirConditioner $record): HtmlString {
                        $record->loadMissing('room.floor.building');

                        $qrCodeString = app(AirConditionerQrCode::class)->renderDataUri($record->asset_code);
                        $printUrl = route('admin.air-conditioners.print-qr', $record);
                        $buildingName = $record->room?->floor?->building?->name ?? '';
                        $roomName = $record->room?->name ?? '';

                        return new HtmlString(sprintf(
                            '<div class="text-center p-4">
                                <img src="%s" alt="QR Code %s" class="mx-auto" style="width: 200px; height: 200px;" />
                                <div class="mt-4 font-bold text-lg text-gray-800 dark:text-gray-200">%s</div>
                                <div class="text-sm text-gray-600 dark:text-gray-400">%s - %s PK</div>
                                <div class="text-sm font-medium text-gray-500">%s - %s</div>
                                <a href="%s" target="_blank" rel="noopener" class="mt-4 inline-flex items-center rounded-lg bg-primary-600 px-4 py-2 text-sm font-semibold text-white">Cetak stiker</a>
                            </div>',
                            e($qrCodeString),
                            e($record->asset_code),
                            e($record->asset_code),
                            e($record->brand),
                            e($record->capacity_pk),
                            e($buildingName),
                            e($roomName),
                            e($printUrl),
                        ));
                    }),
                Action::make('mutateRoom')
                    ->label('Mutasi Ruangan')
                    ->icon('heroicon-o-arrows-right-left')
                    ->color('warning')
                    ->modalHeading('Mutasi ruangan AC')
                    ->form([
                        Forms\Components\Select::make('to_room_id')
                            ->label('Ruangan tujuan')
                            ->options(fn (AirConditioner $record): array => Room::query()
                                ->with('floor.building')
                                ->where('id', '!=', $record->room_id)
                                ->orderBy('name')
                                ->get()
                                ->mapWithKeys(fn (Room $room): array => [
                                    $room->id => "{$room->floor->building->name} - {$room->floor->name} - {$room->name}",
                                ])
                                ->all())
                            ->searchable()
                            ->required(),
                        Forms\Components\DatePicker::make('mutation_date')
                            ->label('Tanggal mutasi')
                            ->default(now())
                            ->required(),
                        Forms\Components\Textarea::make('reason')->label('Alasan'),
                        Forms\Components\TextInput::make('transferred_by')
                            ->label('Dipindahkan oleh')
                            ->default(fn (): ?string => auth()->user()?->name)
                            ->maxLength(255),
                    ])
                    ->action(function (AirConditioner $record, array $data): void {
                        DB::transaction(function () use ($record, $data): void {
                            $record->assetMutations()->create([
                                'from_room_id' => $record->room_id,
                                'to_room_id' => $data['to_room_id'],
                                'mutation_date' => $data['mutation_date'],
                                'reason' => $data['reason'] ?? null,
                                'transferred_by' => $data['transferred_by'] ?? null,
                            ]);

                            $record->update(['room_id' => $data['to_room_id']]);
                        });
                    }),
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListAirConditioners::route('/'),
            'create' => Pages\CreateAirConditioner::route('/create'),
            'view' => Pages\ViewAirConditioner::route('/{record}'),
            'edit' => Pages\EditAirConditioner::route('/{record}/edit'),
        ];
    }

    private static function typeOptions(): array
    {
        return [
            'split_wall' => 'Split wall',
            'cassette' => 'Cassette',
            'standing_floor' => 'Standing floor',
            'split_duct' => 'Split duct',
            'vrv_vrf' => 'VRV / VRF',
        ];
    }

    private static function conditionOptions(): array
    {
        return [
            'baik' => 'Baik',
            'rusak_ringan' => 'Rusak ringan',
            'rusak_berat' => 'Rusak berat',
            'perlu_servis' => 'Perlu servis',
        ];
    }
}
