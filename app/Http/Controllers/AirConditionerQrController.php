<?php

namespace App\Http\Controllers;

use App\Filament\Resources\AirConditionerResource;
use App\Models\AirConditioner;
use App\Services\AirConditionerQrCode;
use Filament\Facades\Filament;
use Filament\Models\Contracts\FilamentUser;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;

class AirConditionerQrController extends Controller
{
    public function lookup(string $assetCode): JsonResponse
    {
        $this->authorizePanelAccess();
        $airConditioner = AirConditioner::query()->where('asset_code', $assetCode)->firstOrFail();

        return response()->json([
            'url' => AirConditionerResource::getUrl('view', ['record' => $airConditioner]),
        ]);
    }

    public function print(AirConditioner $airConditioner, AirConditionerQrCode $qrCode): View
    {
        $this->authorizePanelAccess();
        $airConditioner->load('room.floor.building');

        return view('air-conditioners.print-qr', [
            'record' => $airConditioner,
            'qrCodeString' => $qrCode->renderDataUri($airConditioner->asset_code),
        ]);
    }

    private function authorizePanelAccess(): void
    {
        $panel = Filament::getPanel('admin');
        Filament::setCurrentPanel($panel);
        $user = Filament::auth()->user();

        abort_unless(
            $user && (! $user instanceof FilamentUser || $user->canAccessPanel($panel)),
            403,
        );
    }
}
