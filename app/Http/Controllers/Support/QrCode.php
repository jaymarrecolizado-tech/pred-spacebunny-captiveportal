<?php

namespace App\Http\Controllers\Support;

use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;

/**
 * Server-rendered QR code. SVG keeps it crisp for printing and needs no image
 * library beyond the barcode writer itself.
 */
class QrCode
{
    public static function svg(string $url, int $size = 320): string
    {
        $writer = new Writer(
            new ImageRenderer(
                new RendererStyle($size, 1),
                new SvgImageBackEnd,
            )
        );

        return $writer->writeString($url);
    }
}
