<?php

namespace App\Services;

use chillerlan\QRCode\QRCode;
use chillerlan\QRCode\QROptions;

class AirConditionerQrCode
{
    public function renderDataUri(string $assetCode): string
    {
        $options = new QROptions;
        $options->outputType = QRCode::OUTPUT_MARKUP_SVG;
        $options->scale = 8;

        return (new QRCode($options))->render($assetCode);
    }
}
