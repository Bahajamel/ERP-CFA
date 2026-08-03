<?php

namespace App\Support;

use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;

/**
 * Génère un QR code (SVG, pur PHP — sans extension image) sous forme de data-URI,
 * directement intégrable dans une vue Blade ou un PDF.
 */
class QrCode
{
    /** Data-URI SVG d'un QR code encodant $contenu, de $taille pixels de côté. */
    public static function dataUri(string $contenu, int $taille = 140): string
    {
        $renderer = new ImageRenderer(
            new RendererStyle($taille, 1),
            new SvgImageBackEnd,
        );

        $svg = (new Writer($renderer))->writeString($contenu);

        return 'data:image/svg+xml;base64,'.base64_encode($svg);
    }
}
