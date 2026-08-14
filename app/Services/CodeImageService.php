<?php

namespace App\Services;

use BaconQrCode\Renderer\GDLibRenderer;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer as QrWriter;
use Picqer\Barcode\BarcodeGenerator;
use Picqer\Barcode\BarcodeGeneratorPNG;
use Picqer\Barcode\BarcodeGeneratorSVG;

/**
 * Renders the two scannable marks a product carries.
 *
 * SVG is used on screen because it stays sharp at any zoom; PNG data URIs are
 * used inside PDFs, where dompdf's SVG support is unreliable.
 */
class CodeImageService
{
    /** Code 128 accepts any ASCII; EAN-13 needs exactly 13 digits. */
    protected function barcodeType(string $type): string
    {
        return $type === 'EAN13'
            ? BarcodeGenerator::TYPE_EAN_13
            : BarcodeGenerator::TYPE_CODE_128;
    }

    public function barcodeSvg(
        string $value,
        string $type = 'C128',
        float $widthFactor = 2,
        float $height = 50,
    ): string {
        return (new BarcodeGeneratorSVG())->getBarcode(
            $value,
            $this->barcodeType($type),
            $widthFactor,
            $height,
        );
    }

    /** Barcode as a `data:` URI, safe to drop straight into an <img src>. */
    public function barcodePngDataUri(
        string $value,
        string $type = 'C128',
        int $widthFactor = 2,
        int $height = 50,
    ): string {
        $png = (new BarcodeGeneratorPNG())->getBarcode(
            $value,
            $this->barcodeType($type),
            $widthFactor,
            $height,
        );

        return 'data:image/png;base64,'.base64_encode($png);
    }

    public function qrSvg(string $value, int $size = 220, int $margin = 1): string
    {
        $writer = new QrWriter(
            new ImageRenderer(new RendererStyle($size, $margin), new SvgImageBackEnd())
        );

        return $writer->writeString($value);
    }

    public function qrPngDataUri(string $value, int $size = 220, int $margin = 1): string
    {
        $writer = new QrWriter(new GDLibRenderer($size, $margin));

        return 'data:image/png;base64,'.base64_encode($writer->writeString($value));
    }

    /**
     * Guard against feeding an invalid value to a strict symbology — an
     * EAN-13 renderer throws on anything that is not 13 digits.
     */
    public function isRenderable(string $value, string $type): bool
    {
        if ($value === '') {
            return false;
        }

        if ($type === 'EAN13') {
            return (bool) preg_match('/^\d{13}$/', $value);
        }

        return true;
    }
}
