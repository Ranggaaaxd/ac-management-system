<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\MaintenanceRecordResource;
use App\Models\AirConditioner;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\Concerns\InteractsWithPageFilters;
use Filament\Widgets\TableWidget;
use Illuminate\Database\Eloquent\Builder;

class AcNeedsAttentionWidget extends TableWidget
{
    use InteractsWithPageFilters;

    protected static bool $isLazy = false;

    protected static ?int $sort = 4;

    protected static ?string $heading = 'Unit AC yang Membutuhkan Perhatian';

    protected int|string|array $columnSpan = 'full';

    public function table(Table $table): Table
    {
        return $table
            ->query(fn (): Builder => $this->getAttentionQuery())
            ->description('Unit rusak, belum diservis lebih dari 90 hari, atau memiliki riwayat perbaikan berulang.')
            ->emptyStateHeading('Semua unit dalam kondisi baik')
            ->emptyStateDescription('Tidak ada AC yang membutuhkan tindak lanjut berdasarkan filter saat ini.')
            ->columns([
                Tables\Columns\TextColumn::make('asset_code')
                    ->label('Kode AC')
                    ->searchable(),
                Tables\Columns\TextColumn::make('room.floor.building.name')
                    ->label('Gedung')
                    ->description(fn (AirConditioner $record): string => "{$record->room->floor->name} - {$record->room->name}")
                    ->wrap(),
                Tables\Columns\TextColumn::make('brand')
                    ->label('Merk / PK')
                    ->formatStateUsing(fn (AirConditioner $record): string => "{$record->brand} / {$record->capacity_pk} PK"),
                Tables\Columns\TextColumn::make('last_serviced_at')
                    ->label('Terakhir servis')
                    ->date('d M Y')
                    ->placeholder('Belum pernah'),
                Tables\Columns\TextColumn::make('condition')
                    ->label('Kondisi')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'baik' => 'Baik',
                        'rusak_ringan' => 'Rusak ringan',
                        'rusak_berat' => 'Rusak berat',
                        'perlu_servis' => 'Perlu servis',
                        default => $state,
                    })
                    ->color(fn (string $state): string => match ($state) {
                        'baik' => 'success',
                        'rusak_ringan' => 'warning',
                        'rusak_berat' => 'danger',
                        'perlu_servis' => 'info',
                        default => 'gray',
                    }),
                Tables\Columns\TextColumn::make('attention_status')
                    ->label('Status rekomendasi')
                    ->state(fn (AirConditioner $record): array => self::getRecommendations($record))
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'damaged_light' => 'Rusak ringan',
                        'damaged_heavy' => 'Rusak berat',
                        'needs_service' => 'Perlu servis',
                        'overdue_service' => 'Servis > 90 hari',
                        'repeat_repairs' => 'Evaluasi ganti unit',
                        default => $state,
                    })
                    ->color(fn (string $state): string => match ($state) {
                        'damaged_light', 'overdue_service' => 'warning',
                        'damaged_heavy', 'needs_service', 'repeat_repairs' => 'danger',
                        default => 'gray',
                    }),
            ])
            ->actions([
                Tables\Actions\Action::make('createMaintenance')
                    ->label('Buat servis')
                    ->icon('heroicon-o-wrench-screwdriver')
                    ->url(fn (AirConditioner $record): string => MaintenanceRecordResource::getUrl('create', [
                        'air_conditioner_id' => $record->getKey(),
                    ])),
            ])
            ->defaultSort('last_serviced_at', 'asc')
            ->searchPlaceholder('Cari kode atau merk AC...')
            ->searchDebounce('500ms')
            ->striped()
            ->paginated([5, 10, 25])
            ->defaultPaginationPageOption(5);
    }

    private function getAttentionQuery(): Builder
    {
        $cutoff = now()->subDays(90)->toDateString();

        return AirConditioner::query()
            ->with(['room.floor.building'])
            ->withCount([
                'maintenanceRecords as completed_repair_count' => fn (Builder $query): Builder => $query
                    ->where('status', 'selesai')
                    ->where('type', 'perbaikan'),
            ])
            ->where(function (Builder $query) use ($cutoff): void {
                $query->whereIn('condition', ['rusak_ringan', 'rusak_berat', 'perlu_servis'])
                    ->orWhereNull('last_serviced_at')
                    ->orWhereDate('last_serviced_at', '<=', $cutoff)
                    ->orWhereHas(
                        'maintenanceRecords',
                        fn (Builder $query): Builder => $query
                            ->where('status', 'selesai')
                            ->where('type', 'perbaikan'),
                        '>=',
                        3,
                    );
            });
    }

    private static function getRecommendations(AirConditioner $record): array
    {
        $recommendations = match ($record->condition) {
            'rusak_ringan' => ['damaged_light'],
            'rusak_berat' => ['damaged_heavy'],
            'perlu_servis' => ['needs_service'],
            default => [],
        };

        if (! $record->last_serviced_at || $record->last_serviced_at->lte(now()->subDays(90))) {
            $recommendations[] = 'overdue_service';
        }

        if ($record->completed_repair_count >= 3) {
            $recommendations[] = 'repeat_repairs';
        }

        return $recommendations;
    }
}
