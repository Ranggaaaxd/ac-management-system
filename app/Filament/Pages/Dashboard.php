<?php

namespace App\Filament\Pages;

use Filament\Forms;
use Filament\Forms\Form;
use Filament\Pages\Dashboard as BaseDashboard;
use Filament\Pages\Dashboard\Concerns\HasFiltersForm;

class Dashboard extends BaseDashboard
{
    use HasFiltersForm;

    public function filtersForm(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\DatePicker::make('start_date')
                    ->label('Dari tanggal')
                    ->default(now()->startOfMonth()->toDateString()),
                Forms\Components\DatePicker::make('end_date')
                    ->label('Sampai tanggal')
                    ->default(now()->endOfMonth()->toDateString()),
            ])
            ->columns(2)
            ->statePath('filters')
            ->live();
    }

    public function getColumns(): int|string|array
    {
        return [
            'default' => 1,
            'xl' => 2,
        ];
    }
}
