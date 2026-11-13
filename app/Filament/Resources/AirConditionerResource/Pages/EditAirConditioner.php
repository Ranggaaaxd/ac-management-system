<?php

namespace App\Filament\Resources\AirConditionerResource\Pages;

use App\Filament\Resources\AirConditionerResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Support\Str;

class EditAirConditioner extends EditRecord
{
    protected static string $resource = AirConditionerResource::class;

    protected function mutateFormDataBeforeSave(array $data): array
    {
        if (blank($data['asset_code'] ?? null)) {
            $data['asset_code'] = 'AC-'.Str::upper(Str::random(8));
        }

        return $data;
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
