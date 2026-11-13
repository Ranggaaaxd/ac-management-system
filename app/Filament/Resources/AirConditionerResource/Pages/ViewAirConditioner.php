<?php

namespace App\Filament\Resources\AirConditionerResource\Pages;

use App\Filament\Resources\AirConditionerResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

class ViewAirConditioner extends ViewRecord
{
    protected static string $resource = AirConditionerResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make(),
        ];
    }
}
