<?php

namespace App\Filament\Resources;

use App\Filament\Resources\SparePartResource\Pages;
use App\Filament\Resources\SparePartResource\RelationManagers\MaintenanceSparePartsRelationManager;
use App\Filament\Resources\SparePartResource\RelationManagers\SparePartStockInsRelationManager;
use App\Models\SparePart;
use App\Services\SparePartStockInService;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Infolists\Components\Section;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Infolist;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Enums\FiltersLayout;
use Filament\Tables\Table;

class SparePartResource extends Resource
{
    protected static ?string $model = SparePart::class;

    protected static ?string $navigationGroup = 'Inventori & Stok';

    protected static ?string $navigationIcon = 'heroicon-o-cube';

    protected static ?int $navigationSort = 1;

    protected static ?string $modelLabel = 'Sparepart';

    protected static ?string $pluralModelLabel = 'Sparepart';

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\TextInput::make('name')->label('Nama')->required()->maxLength(255),
            Forms\Components\Select::make('category')
                ->label('Kategori')
                ->options(self::categoryOptions())
                ->required(),
            Forms\Components\TextInput::make('unit')
                ->label('Satuan')
                ->placeholder('pcs, kg, tabung')
                ->required()
                ->maxLength(255),
            Forms\Components\TextInput::make('stock')
                ->label('Stok')
                ->numeric()
                ->minValue(0)
                ->default(0)
                ->required(),
            Forms\Components\TextInput::make('minimum_stock')
                ->label('Stok minimum')
                ->numeric()
                ->minValue(0)
                ->default(1)
                ->required(),
        ])->columns(2);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')->label('Nama')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('category')->label('Kategori')->badge()->formatStateUsing(
                    fn (string $state): string => self::categoryOptions()[$state] ?? $state,
                )->sortable(),
                Tables\Columns\TextColumn::make('stock')
                    ->label('Stok')
                    ->badge()
                    ->formatStateUsing(fn (string $state, SparePart $record): string => "{$state} {$record->unit}")
                    ->color(fn (SparePart $record): string => (int) round((float) $record->stock * 100) <= (int) round((float) $record->minimum_stock * 100)
                        ? 'danger'
                        : 'success')
                    ->sortable(),
                Tables\Columns\TextColumn::make('minimum_stock')->label('Stok minimum')->sortable(),
                Tables\Columns\TextColumn::make('unit')->label('Satuan')->toggleable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('category')
                    ->label('Kategori')
                    ->options(self::categoryOptions()),
            ], layout: FiltersLayout::Modal)
            ->defaultSort('name')
            ->actions([
                Tables\Actions\Action::make('addStock')
                    ->label('Tambah Stok')
                    ->icon('heroicon-m-plus-circle')
                    ->color('success')
                    ->form(self::stockInForm())
                    ->action(function (SparePart $record, array $data): void {
                        app(SparePartStockInService::class)->add($record, $data);
                        Notification::make()->success()->title('Stok berhasil ditambahkan')->send();
                    }),
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist->schema([
            Section::make('Informasi Sparepart')
                ->schema([
                    TextEntry::make('name')->label('Nama'),
                    TextEntry::make('category')
                        ->label('Kategori')
                        ->formatStateUsing(fn (string $state): string => self::categoryOptions()[$state] ?? $state),
                    TextEntry::make('stock')->label('Stok')->suffix(fn (SparePart $record): string => ' '.$record->unit),
                    TextEntry::make('minimum_stock')->label('Stok minimum')->suffix(fn (SparePart $record): string => ' '.$record->unit),
                ])
                ->columns(2),
        ]);
    }

    public static function getRelations(): array
    {
        return [
            SparePartStockInsRelationManager::class,
            MaintenanceSparePartsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListSpareParts::route('/'),
            'create' => Pages\CreateSparePart::route('/create'),
            'view' => Pages\ViewSparePart::route('/{record}'),
            'edit' => Pages\EditSparePart::route('/{record}/edit'),
        ];
    }

    private static function categoryOptions(): array
    {
        return [
            'freon' => 'Freon',
            'kapasitor' => 'Kapasitor',
            'pipa' => 'Pipa',
            'lainnya' => 'Lainnya',
        ];
    }

    /**
     * @return array<int, Forms\Components\Component>
     */
    public static function stockInForm(): array
    {
        return [
            Forms\Components\TextInput::make('quantity')
                ->label('Jumlah masuk')
                ->numeric()
                ->minValue(0.01)
                ->required(),
            Forms\Components\DatePicker::make('incoming_date')
                ->label('Tanggal masuk')
                ->default(today())
                ->required(),
            Forms\Components\TextInput::make('supplier')
                ->label('Supplier / sumber')
                ->maxLength(255),
            Forms\Components\TextInput::make('purchase_price')
                ->label('Harga beli per unit')
                ->numeric()
                ->minValue(0)
                ->prefix('Rp'),
            Forms\Components\Textarea::make('notes')
                ->label('Catatan / nomor nota')
                ->rows(3)
                ->columnSpanFull(),
        ];
    }
}
