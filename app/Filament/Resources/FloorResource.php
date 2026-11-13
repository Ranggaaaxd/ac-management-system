<?php

namespace App\Filament\Resources;

use App\Filament\Resources\FloorResource\Pages;
use App\Models\Building;
use App\Models\Floor;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class FloorResource extends Resource
{
    protected static ?string $model = Floor::class;

    protected static ?string $navigationGroup = 'Master Data';

    protected static ?string $navigationIcon = 'heroicon-o-building-office';

    protected static ?int $navigationSort = 2;

    protected static ?string $modelLabel = 'Lantai';

    protected static ?string $pluralModelLabel = 'Lantai';

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Select::make('building_id')
                ->label('Gedung')
                ->options(Building::query()->orderBy('name')->pluck('name', 'id'))
                ->searchable()
                ->preload()
                ->required(),
            Forms\Components\TextInput::make('name')
                ->label('Nama lantai')
                ->required()
                ->maxLength(255),
            Forms\Components\TextInput::make('floor_number')
                ->label('Nomor lantai')
                ->numeric()
                ->integer()
                ->required(),
        ])->columns(2);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('building.name')->label('Gedung')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('name')->label('Lantai')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('floor_number')->label('Nomor')->sortable(),
                Tables\Columns\TextColumn::make('rooms_count')->counts('rooms')->label('Jumlah ruangan')->sortable(),
            ])
            ->defaultSort('floor_number')
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
            'index' => Pages\ListFloors::route('/'),
            'create' => Pages\CreateFloor::route('/create'),
            'edit' => Pages\EditFloor::route('/{record}/edit'),
        ];
    }
}
