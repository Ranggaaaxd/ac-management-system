<?php

namespace App\Filament\Resources;

use App\Filament\Resources\SparePartResource\Pages;
use App\Models\SparePart;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class SparePartResource extends Resource
{
    protected static ?string $model = SparePart::class;

    protected static ?string $navigationGroup = 'Master Data';

    protected static ?string $navigationIcon = 'heroicon-o-cube';

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
            ])
            ->defaultSort('name')
            ->actions([
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
            'index' => Pages\ListSpareParts::route('/'),
            'create' => Pages\CreateSparePart::route('/create'),
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
}
