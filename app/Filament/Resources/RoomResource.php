<?php

namespace App\Filament\Resources;

use App\Filament\Resources\RoomResource\Pages;
use App\Models\Floor;
use App\Models\Room;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class RoomResource extends Resource
{
    protected static ?string $model = Room::class;

    protected static ?string $navigationGroup = 'Master Data';

    protected static ?string $navigationIcon = 'heroicon-o-rectangle-group';

    protected static ?int $navigationSort = 3;

    protected static ?string $modelLabel = 'Ruangan';

    protected static ?string $pluralModelLabel = 'Ruangan';

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Select::make('floor_id')
                ->label('Lantai')
                ->options(Floor::query()->with('building')->orderBy('floor_number')->get()->mapWithKeys(
                    fn (Floor $floor): array => [
                        $floor->id => "{$floor->building->name} - {$floor->name}",
                    ],
                ))
                ->searchable()
                ->preload()
                ->required(),
            Forms\Components\TextInput::make('name')
                ->label('Nama ruangan')
                ->required()
                ->maxLength(255),
        ])->columns(2);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('floor.building.name')->label('Gedung')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('floor.name')->label('Lantai')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('name')->label('Ruangan')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('air_conditioners_count')->counts('airConditioners')->label('Jumlah AC')->sortable(),
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
            'index' => Pages\ListRooms::route('/'),
            'create' => Pages\CreateRoom::route('/create'),
            'edit' => Pages\EditRoom::route('/{record}/edit'),
        ];
    }
}
