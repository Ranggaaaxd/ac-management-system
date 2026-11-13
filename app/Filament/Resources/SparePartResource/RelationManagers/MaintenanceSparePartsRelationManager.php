<?php

namespace App\Filament\Resources\SparePartResource\RelationManagers;

use App\Models\MaintenanceRecord;
use App\Models\MaintenanceSparePart;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class MaintenanceSparePartsRelationManager extends RelationManager
{
    protected static string $relationship = 'maintenanceSpareParts';

    protected static ?string $title = 'Riwayat pemakaian sparepart';

    public function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query
                ->with([
                    'maintenanceRecord.airConditioner.room.floor.building',
                    'maintenanceRecord.technician',
                    'sparePart',
                ])
                ->whereHas('maintenanceRecord')
                ->orderByDesc(
                    MaintenanceRecord::select('performed_at')
                        ->whereColumn('maintenance_records.id', 'maintenance_spare_parts.maintenance_record_id')
                        ->limit(1),
                ))
            ->columns([
                Tables\Columns\TextColumn::make('maintenanceRecord.performed_at')
                    ->label('Tanggal pengerjaan')
                    ->date('d M Y')
                    ->sortable(),
                Tables\Columns\TextColumn::make('maintenanceRecord.airConditioner.asset_code')
                    ->label('Unit AC')
                    ->formatStateUsing(fn (?string $state, MaintenanceSparePart $record): string => sprintf(
                        '%s - %s / %s PK',
                        $state ?? '—',
                        $record->maintenanceRecord->airConditioner->brand,
                        $record->maintenanceRecord->airConditioner->capacity_pk,
                    )),
                Tables\Columns\TextColumn::make('maintenanceRecord.airConditioner.room.floor.building.name')
                    ->label('Lokasi')
                    ->formatStateUsing(fn (?string $state, MaintenanceSparePart $record): string => implode(' - ', [
                        $state ?? '',
                        $record->maintenanceRecord->airConditioner->room->floor->name,
                        $record->maintenanceRecord->airConditioner->room->name,
                    ]))
                    ->wrap(),
                Tables\Columns\TextColumn::make('quantity')
                    ->label('Jumlah terpakai')
                    ->formatStateUsing(fn (string $state, MaintenanceSparePart $record): string => sprintf(
                        '%s %s',
                        rtrim(rtrim(number_format((float) $state, 2, ',', '.'), '0'), ','),
                        $record->sparePart->unit,
                    )),
                Tables\Columns\TextColumn::make('maintenanceRecord.technician.name')
                    ->label('Teknisi')
                    ->default('—'),
                Tables\Columns\TextColumn::make('maintenanceRecord.status')
                    ->label('Status servis')
                    ->badge()
                    ->formatStateUsing(fn (?string $state): string => $state === 'selesai' ? 'Selesai' : 'Draft')
                    ->color(fn (?string $state): string => $state === 'selesai' ? 'success' : 'gray'),
            ])
            ->actions([
                Tables\Actions\Action::make('viewMaintenanceRecord')
                    ->label('Lihat catatan servis')
                    ->icon('heroicon-o-eye')
                    ->modalHeading(fn (MaintenanceSparePart $record): string => 'Catatan servis '.$record->maintenanceRecord->airConditioner->asset_code)
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Tutup')
                    ->modalContent(fn (MaintenanceSparePart $record) => view(
                        'filament.resources.spare-part-resource.maintenance-record-details',
                        ['maintenanceRecord' => $record->maintenanceRecord],
                    )),
            ])
            ->headerActions([])
            ->bulkActions([]);
    }
}
