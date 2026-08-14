<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Services\CodeImageService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * Serves the barcode and QR images for a product.
 *
 * Reachable from either guard, because the terminal prints labels too. The
 * response is cached for a day — the marks only change when the operator
 * explicitly regenerates them.
 */
class MediaController extends Controller
{
    public function __construct(
        protected CodeImageService $codes,
    ) {}

    protected function guardAccess(): void
    {
        if (! auth('web')->check() && ! auth('pos')->check()) {
            abort(403);
        }
    }

    public function barcode(Request $request, Product $product): Response
    {
        $this->guardAccess();

        $value = (string) ($product->barcode_value ?: $product->sku);
        $type = $product->barcode_type ?: 'C128';

        if (! $this->codes->isRenderable($value, $type)) {
            // Fall back to Code 128 rather than failing the whole label page.
            $type = 'C128';
        }

        $svg = $this->codes->barcodeSvg(
            $value,
            $type,
            (float) $request->query('w', 2),
            (float) $request->query('h', 50),
        );

        return response($svg, 200, [
            'Content-Type' => 'image/svg+xml',
            'Cache-Control' => 'private, max-age=86400',
        ]);
    }

    public function qr(Request $request, Product $product): Response
    {
        $this->guardAccess();

        $svg = $this->codes->qrSvg(
            (string) ($product->qr_value ?: $product->sku),
            (int) $request->query('size', 220),
        );

        return response($svg, 200, [
            'Content-Type' => 'image/svg+xml',
            'Cache-Control' => 'private, max-age=86400',
        ]);
    }
}
