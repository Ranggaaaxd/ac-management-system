<?php

namespace App\Filament\Resources\AirConditionerResource\RelationManagers;

use App\Filament\Resources\MaintenanceRecordResource;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Columns\Summarizers\Summarizer;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class MaintenanceRecordsRelationManager extends RelationManager
{
    protected static string $relationship = 'maintenanceRecords';

    protected static ?string $title = 'Rekam medis / riwayat servis';

    public function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query
                ->with(['technician', 'maintenanceSpareParts'])
                ->orderByDesc('performed_at'))
            ->columns([
                Tables\Columns\TextColumn::make('performed_at')->label('Tanggal servis')->date()->sortable(),
                Tables\Columns\TextColumn::make('type')
                    ->label('Pengerjaan')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => MaintenanceRecordResource::typeOptions()[$state] ?? $state),
                Tables\Columns\TextColumn::make('technician.name')->label('Teknisi')->default('—'),
                Tables\Columns\TextColumn::make('issue_description')->label('Keluhan / Diagnosa')->limit(60)->placeholder('—'),
                Tables\Columns\TextColumn::make('action_taken')->label('Tindakan')->limit(60)->placeholder('—'),
                Tables\Columns\TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => $state === 'selesai' ? 'Selesai' : 'Draft')
                    ->color(fn (string $state): string => $state === 'selesai' ? 'success' : 'gray'),
                Tables\Columns\TextColumn::make('total_cost')
                    ->label('Total biaya')
                    ->state(fn ($record): float => (float) $record->total_cost)
                    ->money('IDR')
                    ->summarize(
                        Summarizer::make()
                            ->label('Total biaya riwayat')
                            ->money('IDR')
                            ->using(function (\Illuminate\Database\Query\Builder $query): float {
                                $recordIds = (clone $query)->select('id');
                                $labor = (clone $query)->sum('labor_cost');
                                $parts = DB::table('maintenance_spare_parts')
                                    ->whereIn('maintenance_record_id', $recordIds)
                                    ->sum('subtotal');

                                return (float) $labor + (float) $parts;
                            }),
                    ),
            ])
            ->headerActions([])
            ->actions([])
            ->bulkActions([]);
    }
}
