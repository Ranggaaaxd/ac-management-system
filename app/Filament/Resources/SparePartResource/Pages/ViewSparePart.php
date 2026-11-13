<?php

namespace App\Filament\Resources\SparePartResource\Pages;

use App\Filament\Resources\SparePartResource;
use App\Services\SparePartStockInService;
use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;

class ViewSparePart extends ViewRecord
{
    protected static string $resource = SparePartResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('addStock')
                ->label('Tambah Stok')
                ->icon('heroicon-m-plus-circle')
                ->color('success')
                ->form(SparePartResource::stockInForm())
                ->action(function (array $data): void {
                    app(SparePartStockInService::class)->add($this->getRecord(), $data);
                    Notification::make()->success()->title('Stok berhasil ditambahkan')->send();
                    $this->refreshFormData(['stock']);
                }),
            Actions\EditAction::make(),
        ];
    }
}
