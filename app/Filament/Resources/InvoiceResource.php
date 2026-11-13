<?php

namespace App\Filament\Resources;

use App\Filament\Resources\InvoiceResource\Pages;
use App\Models\Building;
use App\Models\Invoice;
use App\Models\MaintenanceRecord;
use App\Services\InvoiceBilling;
use Filament\Actions\Action as HeaderAction;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Forms\Set;
use Filament\Infolists\Components\Section as InfolistSection;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Infolist;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Actions\Action as TableAction;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;
use pxlrbt\FilamentExcel\Actions\Tables\ExportAction;

class InvoiceResource extends Resource
{
    protected static ?string $model = Invoice::class;

    protected static ?string $navigationGroup = 'Keuangan';

    protected static ?string $navigationIcon = 'heroicon-o-document-currency-dollar';

    protected static ?string $modelLabel = 'Invoice';

    protected static ?string $pluralModelLabel = 'Invoice';

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('Informasi Utama')
                ->schema([
                    Forms\Components\TextInput::make('invoice_number')
                        ->label('Nomor invoice')
                        ->default(fn (): string => self::generateInvoiceNumber())
                        ->required()
                        ->unique(ignoreRecord: true)
                        ->maxLength(255),
                    Forms\Components\Select::make('building_id')
                        ->label('Gedung')
                        ->options(fn (): array => Building::query()->orderBy('name')->pluck('name', 'id')->all())
                        ->searchable()
                        ->live()
                        ->afterStateUpdated(function (Get $get, Set $set, ?Invoice $record): void {
                            $availableIds = app(InvoiceBilling::class)
                                ->availableMaintenanceRecords(
                                    filled($get('building_id')) ? (int) $get('building_id') : null,
                                    $record?->id,
                                )
                                ->modelKeys();
                            $selectedIds = array_values(array_intersect(
                                array_map('intval', $get('maintenance_record_ids') ?? []),
                                $availableIds,
                            ));

                            $set('maintenance_record_ids', $selectedIds);
                            $set('total_amount', app(InvoiceBilling::class)->totalFor($selectedIds));
                        }),
                    Forms\Components\DatePicker::make('invoice_date')
                        ->label('Tanggal invoice')
                        ->default(now())
                        ->required(),
                    Forms\Components\DatePicker::make('due_date')
                        ->label('Jatuh tempo')
                        ->default(now()->addDays(14)),
                    Forms\Components\Select::make('payment_status')
                        ->label('Status pembayaran')
                        ->options(self::paymentStatusOptions())
                        ->default('unpaid')
                        ->required(),
                    Forms\Components\Textarea::make('notes')
                        ->label('Catatan / instruksi pembayaran')
                        ->rows(3)
                        ->columnSpanFull(),
                ])
                ->columns(2),
            Forms\Components\Section::make('Pekerjaan servis')
                ->description('Hanya pekerjaan selesai yang belum ditagihkan atau sudah ditambahkan ke invoice ini.')
                ->schema([
                    Forms\Components\CheckboxList::make('maintenance_record_ids')
                        ->label('Pilih pekerjaan')
                        ->options(function (Get $get, ?Invoice $record): array {
                            return app(InvoiceBilling::class)
                                ->availableMaintenanceRecords(
                                    filled($get('building_id')) ? (int) $get('building_id') : null,
                                    $record?->id,
                                )
                                ->mapWithKeys(fn (MaintenanceRecord $maintenanceRecord): array => [
                                    $maintenanceRecord->id => self::maintenanceRecordLabel($maintenanceRecord),
                                ])
                                ->all();
                        })
                        ->default(fn (?Invoice $record): array => $record
                            ? $record->maintenanceRecords()->pluck('id')->map(fn ($id): string => (string) $id)->all()
                            : [])
                        ->columns(1)
                        ->bulkToggleable()
                        ->live()
                        ->afterStateUpdated(fn (Get $get, Set $set): mixed => $set(
                            'total_amount',
                            app(InvoiceBilling::class)->totalFor($get('maintenance_record_ids') ?? []),
                        )),
                    Forms\Components\TextInput::make('total_amount')
                        ->label('Total tagihan')
                        ->numeric()
                        ->prefix('Rp')
                        ->disabled()
                        ->dehydrated(),
                ]),
        ]);
    }

    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist->schema([
            InfolistSection::make('Informasi Invoice')
                ->schema([
                    TextEntry::make('invoice_number')->label('Nomor invoice'),
                    TextEntry::make('building.name')->label('Gedung')->placeholder('—'),
                    TextEntry::make('invoice_date')->label('Tanggal invoice')->date(),
                    TextEntry::make('due_date')->label('Jatuh tempo')->date()->placeholder('—'),
                    TextEntry::make('payment_status')
                        ->label('Status pembayaran')
                        ->badge()
                        ->formatStateUsing(fn (string $state): string => self::paymentStatusOptions()[$state] ?? $state)
                        ->color(fn (string $state): string => match ($state) {
                            'paid' => 'success',
                            'canceled' => 'danger',
                            default => 'warning',
                        }),
                    TextEntry::make('total_amount')->label('Total tagihan')->money('IDR'),
                    TextEntry::make('notes')->label('Catatan')->columnSpanFull()->placeholder('—'),
                ])
                ->columns(2),
            InfolistSection::make('Pekerjaan yang ditagihkan')
                ->schema([
                    TextEntry::make('maintenance_records_summary')
                        ->label('')
                        ->listWithLineBreaks()
                        ->bulleted()
                        ->state(fn (Invoice $record): array => $record->maintenanceRecords
                            ->map(fn (MaintenanceRecord $maintenanceRecord): string => self::maintenanceRecordLabel($maintenanceRecord))
                            ->all())
                        ->placeholder('Belum ada pekerjaan yang dipilih.'),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('invoice_number')->label('Nomor invoice')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('building.name')->label('Gedung')->default('—')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('invoice_date')->label('Tanggal invoice')->date()->sortable(),
                Tables\Columns\TextColumn::make('due_date')->label('Jatuh tempo')->date()->placeholder('—')->sortable(),
                Tables\Columns\TextColumn::make('total_amount')->label('Total')->money('IDR')->sortable(),
                Tables\Columns\TextColumn::make('payment_status')
                    ->label('Pembayaran')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => self::paymentStatusOptions()[$state] ?? $state)
                    ->color(fn (string $state): string => match ($state) {
                        'paid' => 'success',
                        'canceled' => 'danger',
                        default => 'warning',
                    })
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('payment_status')
                    ->label('Status pembayaran')
                    ->options(self::paymentStatusOptions()),
                Tables\Filters\SelectFilter::make('building_id')
                    ->label('Gedung')
                    ->relationship('building', 'name')
                    ->searchable()
                    ->preload(),
                Tables\Filters\Filter::make('invoice_date')
                    ->label('Rentang tanggal invoice')
                    ->form([
                        Forms\Components\DatePicker::make('from')->label('Dari'),
                        Forms\Components\DatePicker::make('until')->label('Sampai'),
                    ])
                    ->query(fn (Builder $query, array $data): Builder => $query
                        ->when($data['from'] ?? null, fn (Builder $query, string $date): Builder => $query->whereDate('invoice_date', '>=', $date))
                        ->when($data['until'] ?? null, fn (Builder $query, string $date): Builder => $query->whereDate('invoice_date', '<=', $date))),
            ])
            ->headerActions([
                ExportAction::make()->label('Export Excel'),
            ])
            ->actions([
                self::pdfTableAction('printInvoice', 'Cetak Invoice (PDF)', 'heroicon-o-document-text', 'invoice'),
                self::pdfTableAction('printBast', 'Cetak BAST (PDF)', 'heroicon-o-clipboard-document-check', 'bast'),
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListInvoices::route('/'),
            'create' => Pages\CreateInvoice::route('/create'),
            'view' => Pages\ViewInvoice::route('/{record}'),
            'edit' => Pages\EditInvoice::route('/{record}/edit'),
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function paymentStatusOptions(): array
    {
        return [
            'unpaid' => 'Belum dibayar',
            'paid' => 'Lunas',
            'canceled' => 'Dibatalkan',
        ];
    }

    public static function generateInvoiceNumber(): string
    {
        do {
            $number = 'INV/'.now()->format('Ymd').'/'.Str::upper(Str::random(4));
        } while (Invoice::query()->where('invoice_number', $number)->exists());

        return $number;
    }

    public static function pdfTableAction(string $name, string $label, string $icon, string $document): TableAction
    {
        return TableAction::make($name)
            ->label($label)
            ->icon($icon)
            ->url(fn (Invoice $record): string => route("admin.invoices.{$document}", $record))
            ->openUrlInNewTab();
    }

    public static function pdfHeaderAction(string $name, string $label, string $icon, string $document): HeaderAction
    {
        return HeaderAction::make($name)
            ->label($label)
            ->icon($icon)
            ->url(fn (Invoice $record): string => route("admin.invoices.{$document}", $record))
            ->openUrlInNewTab();
    }

    private static function maintenanceRecordLabel(MaintenanceRecord $record): string
    {
        $airConditioner = $record->airConditioner;

        return sprintf(
            '%s | %s %s PK | %s - %s | %s | %s',
            $record->performed_at->format('d/m/Y'),
            $airConditioner->brand,
            $airConditioner->capacity_pk,
            $airConditioner->room->floor->building->name,
            $airConditioner->room->name,
            str($record->type)->replace('_', ' ')->title(),
            'Rp '.number_format($record->total_cost, 0, ',', '.'),
        );
    }
}
