<?php

namespace App\Filament\Pages;

use App\Filament\Resources\MaintenanceRecordResource;
use App\Models\MaintenanceRecord;
use Filament\Actions\Action;
use Filament\Pages\Page;
use Filament\Tables;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class ActiveMaintenance extends Page implements HasTable
{
    use InteractsWithTable;

    protected static ?string $navigationGroup = 'Servis AC';

    protected static ?string $navigationIcon = 'heroicon-o-wrench-screwdriver';

    protected static ?string $navigationLabel = 'Servis AC';

    protected static ?string $title = 'Servis Baru / Sedang Berjalan';

    protected static ?int $navigationSort = 1;

    protected static string $view = 'filament.pages.active-maintenance';

    public function table(Table $table): Table
    {
        return $table
            ->query(fn (): Builder => MaintenanceRecord::query()
                ->where('status', 'draft')
                ->with(['airConditioner.room.floor.building', 'technician']))
            ->defaultSort('performed_at', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('performed_at')
                    ->label('Tanggal')
                    ->date('d M Y')
                    ->sortable(),
                Tables\Columns\TextColumn::make('airConditioner.asset_code')
                    ->label('Unit AC')
                    ->formatStateUsing(fn (?string $state, MaintenanceRecord $record): string => sprintf(
                        '%s - %s',
                        $state ?? '—',
                        $record->airConditioner->brand,
                    ))
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('airConditioner.room.floor.building.name')
                    ->label('Lokasi')
                    ->formatStateUsing(fn (?string $state, MaintenanceRecord $record): string => implode(' - ', [
                        $state ?? '',
                        $record->airConditioner->room->floor->name,
                        $record->airConditioner->room->name,
                    ]))
                    ->wrap(),
                Tables\Columns\TextColumn::make('type')
                    ->label('Pengerjaan')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => MaintenanceRecordResource::typeOptions()[$state] ?? $state),
                Tables\Columns\TextColumn::make('technician.name')
                    ->label('Teknisi')
                    ->default('—'),
                Tables\Columns\TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->formatStateUsing(fn (): string => 'Sedang dikerjakan')
                    ->color('warning'),
            ])
            ->actions([
                Tables\Actions\EditAction::make()
                    ->url(fn (MaintenanceRecord $record): string => MaintenanceRecordResource::getUrl('edit', ['record' => $record])),
            ])
            ->emptyStateHeading('Tidak ada servis yang sedang berjalan')
            ->emptyStateDescription('Buat servis baru untuk mulai mencatat pengerjaan.');
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('createMaintenance')
                ->label('Buat Servis Baru')
                ->icon('heroicon-m-plus')
                ->color('primary')
                ->url(MaintenanceRecordResource::getUrl('create')),
        ];
    }
}
