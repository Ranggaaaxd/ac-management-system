<?php

namespace App\Filament\Resources\SparePartResource\RelationManagers;

use App\Models\SparePartStockIn;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class SparePartStockInsRelationManager extends RelationManager
{
    protected static string $relationship = 'stockIns';

    protected static ?string $title = 'Riwayat Stok Masuk';

    public function table(Table $table): Table
    {
        return $table
            ->defaultSort('incoming_date', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('incoming_date')
                    ->label('Tanggal masuk')
                    ->date('d M Y')
                    ->sortable(),
                Tables\Columns\TextColumn::make('quantity')
                    ->label('Jumlah masuk')
                    ->formatStateUsing(fn (string $state, SparePartStockIn $record): string => sprintf(
                        '%s %s',
                        rtrim(rtrim(number_format((float) $state, 2, ',', '.'), '0'), ','),
                        $record->sparePart->unit,
                    )),
                Tables\Columns\TextColumn::make('supplier')
                    ->label('Supplier')
                    ->placeholder('—')
                    ->searchable(),
                Tables\Columns\TextColumn::make('purchase_price')
                    ->label('Harga beli / unit')
                    ->money('IDR')
                    ->placeholder('—'),
                Tables\Columns\TextColumn::make('notes')
                    ->label('Catatan')
                    ->placeholder('—')
                    ->wrap(),
            ])
            ->headerActions([])
            ->actions([])
            ->bulkActions([]);
    }
}
