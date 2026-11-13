<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\AirConditionerResource;
use App\Filament\Resources\InvoiceResource;
use App\Filament\Resources\MaintenanceRecordResource;
use App\Filament\Resources\SparePartResource;
use Filament\Widgets\Widget;

class DashboardQuickActionsWidget extends Widget
{
    protected static bool $isLazy = false;

    protected static ?int $sort = 0;

    protected int|string|array $columnSpan = 'full';

    protected static string $view = 'filament.widgets.dashboard-quick-actions';

    protected function getViewData(): array
    {
        return [
            'actions' => [
                [
                    'label' => 'Catat Servis Baru',
                    'icon' => 'heroicon-m-wrench-screwdriver',
                    'color' => 'primary',
                    'url' => MaintenanceRecordResource::getUrl('create'),
                ],
                [
                    'label' => 'Tambah Unit AC',
                    'icon' => 'heroicon-m-plus',
                    'color' => 'info',
                    'url' => AirConditionerResource::getUrl('create'),
                ],
                [
                    'label' => 'Tambah Stok Barang',
                    'icon' => 'heroicon-m-archive-box',
                    'color' => 'success',
                    'url' => SparePartResource::getUrl('index'),
                ],
                [
                    'label' => 'Buat Invoice Tagihan',
                    'icon' => 'heroicon-m-document-plus',
                    'color' => 'gray',
                    'url' => InvoiceResource::getUrl('create'),
                ],
            ],
        ];
    }
}
