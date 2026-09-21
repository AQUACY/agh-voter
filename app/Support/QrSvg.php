<?php

namespace App\Support;

use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;

class QrSvg
{
    public static function dataUri(string $payload, int $size = 128): string
    {
        return 'data:image/svg+xml;base64,'.base64_encode(self::svg($payload, $size));
    }

    public static function svg(string $payload, int $size = 128): string
    {
        $renderer = new ImageRenderer(
            new RendererStyle($size, 1),
            new SvgImageBackEnd
        );

        return (new Writer($renderer))->writeString($payload);
    }
}
