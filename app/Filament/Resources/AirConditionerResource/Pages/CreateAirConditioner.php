<?php

namespace App\Filament\Resources\AirConditionerResource\Pages;

use App\Filament\Resources\AirConditionerResource;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Str;

class CreateAirConditioner extends CreateRecord
{
    protected static string $resource = AirConditionerResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        if (blank($data['asset_code'] ?? null)) {
            $data['asset_code'] = 'AC-'.Str::upper(Str::random(8));
        }

        return $data;
    }
}
