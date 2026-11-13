<?php

namespace App\Filament\Pages;

use App\Filament\Resources\MaintenanceRecordResource;
use App\Models\AirConditioner;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Filament\Pages\Page;
use Filament\Tables;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class MaintenanceSchedule extends Page implements HasTable
{
    use InteractsWithTable;

    protected static ?string $navigationGroup = 'Servis AC';

    protected static ?string $navigationIcon = 'heroicon-o-calendar-days';

    protected static ?string $navigationLabel = 'Jadwal Servis Berkala';

    protected static ?string $title = 'Jadwal Servis Berkala';

    protected static ?int $navigationSort = 3;

    protected static string $view = 'filament.pages.maintenance-schedule';

    public function getDueThisWeekCount(): int
    {
        return $this->dueBetween(today(), today()->endOfWeek());
    }

    public function getDueThisMonthCount(): int
    {
        return $this->dueBetween(today(), today()->endOfMonth());
    }

    public function getOverdueCount(): int
    {
        return AirConditioner::query()
            ->where(function (Builder $query): void {
                $query->whereNull('last_serviced_at')
                    ->orWhereDate('last_serviced_at', '<', today()->subDays(90));
            })
            ->count();
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(fn (): Builder => AirConditioner::query()->with(['room.floor.building']))
            ->defaultSort('last_serviced_at', 'asc')
            ->columns([
                Tables\Columns\TextColumn::make('asset_code')
                    ->label('Kode AC')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('room.name')
                    ->label('Lokasi')
                    ->formatStateUsing(fn (?string $state, AirConditioner $record): string => implode(' - ', [
                        $record->room->floor->building->name,
                        $record->room->floor->name,
                        $state ?? '',
                    ]))
                    ->wrap(),
                Tables\Columns\TextColumn::make('last_serviced_at')
                    ->label('Tanggal terakhir servis')
                    ->date('d M Y')
                    ->placeholder('Belum pernah'),
                Tables\Columns\TextColumn::make('next_service_due')
                    ->label('Estimasi jatuh tempo')
                    ->state(fn (AirConditioner $record): ?CarbonImmutable => $record->last_serviced_at
                        ? CarbonImmutable::parse($record->last_serviced_at)->addDays(90)
                        : null)
                    ->date('d M Y')
                    ->placeholder('Perlu servis awal'),
                Tables\Columns\TextColumn::make('schedule_status')
                    ->label('Status')
                    ->state(fn (AirConditioner $record): string => self::statusFor($record))
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'Terlambat' => 'danger',
                        'Perlu Cuci' => 'warning',
                        default => 'success',
                    }),
            ])
            ->actions([
                Tables\Actions\Action::make('scheduleService')
                    ->label('Jadwalkan Servis')
                    ->icon('heroicon-m-calendar')
                    ->color('primary')
                    ->url(fn (AirConditioner $record): string => MaintenanceRecordResource::getUrl('create', [
                        'air_conditioner_id' => $record->id,
                    ])),
            ])
            ->emptyStateHeading('Belum ada unit AC')
            ->emptyStateDescription('Data unit AC akan muncul di halaman jadwal setelah ditambahkan.');
    }

    private function dueBetween(CarbonInterface $start, CarbonInterface $end): int
    {
        return AirConditioner::query()
            ->whereNotNull('last_serviced_at')
            ->whereDate('last_serviced_at', '>=', $start->copy()->subDays(90)->toDateString())
            ->whereDate('last_serviced_at', '<=', $end->copy()->subDays(90)->toDateString())
            ->count();
    }

    private static function statusFor(AirConditioner $record): string
    {
        if (! $record->last_serviced_at) {
            return 'Terlambat';
        }

        $dueDate = CarbonImmutable::parse($record->last_serviced_at)->addDays(90);

        if ($dueDate->isBefore(today())) {
            return 'Terlambat';
        }

        return $dueDate->lessThanOrEqualTo(today()->addDays(30))
            ? 'Perlu Cuci'
            : 'Aman';
    }
}
