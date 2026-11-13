<?php

namespace App\Filament\Resources;

use App\Filament\Resources\BuildingResource\Pages;
use App\Models\Building;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class BuildingResource extends Resource
{
    protected static ?string $model = Building::class;

    protected static ?string $navigationGroup = 'Master Data';

    protected static ?string $navigationIcon = 'heroicon-o-building-office-2';

    protected static ?string $modelLabel = 'Gedung';

    protected static ?string $pluralModelLabel = 'Gedung';

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\TextInput::make('name')
                ->label('Nama gedung')
                ->required()
                ->maxLength(255),
            Forms\Components\Textarea::make('address')
                ->label('Alamat')
                ->columnSpanFull(),
            Forms\Components\Textarea::make('notes')
                ->label('Catatan')
                ->columnSpanFull(),
        ])->columns(2);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')->label('Nama gedung')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('address')->label('Alamat')->limit(50)->toggleable(),
                Tables\Columns\TextColumn::make('floors_count')->counts('floors')->label('Jumlah lantai')->sortable(),
                Tables\Columns\TextColumn::make('created_at')->label('Dibuat')->dateTime()->sortable()->toggleable(isToggledHiddenByDefault: true),
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
            'index' => Pages\ListBuildings::route('/'),
            'create' => Pages\CreateBuilding::route('/create'),
            'edit' => Pages\EditBuilding::route('/{record}/edit'),
        ];
    }
}
