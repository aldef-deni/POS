<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>Label Barcode Produk</title>
    <style>
        /* A4 sheet of peel-off labels, sized to print at 1:1. */
        * { box-sizing: border-box; }

        body {
            margin: 0;
            font-family: "Segoe UI", Arial, sans-serif;
            background: #f1f5f9;
            color: #0f172a;
        }

        .bar {
            padding: 14px 20px;
            background: #fff;
            border-bottom: 1px solid #e2e8f0;
            display: flex; align-items: center; justify-content: space-between; gap: 14px;
            position: sticky; top: 0;
        }
        .bar h1 { font-size: 15px; margin: 0; }
        .bar p { margin: 2px 0 0; font-size: 12px; color: #64748b; }
        .bar button {
            padding: 9px 16px; border: 0; border-radius: 8px;
            background: #4f46e5; color: #fff; font-size: 13px; font-weight: 600; cursor: pointer;
        }

        .sheet {
            width: 210mm;
            margin: 16px auto;
            padding: 8mm 6mm;
            background: #fff;
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 3mm;
        }

        .label {
            border: 1px dashed #cbd5e1;
            border-radius: 3px;
            padding: 3mm 2mm;
            text-align: center;
            page-break-inside: avoid;
            break-inside: avoid;
            display: flex; flex-direction: column; align-items: center; justify-content: center;
            min-height: 26mm;
        }

        .label__name {
            font-size: 8pt; font-weight: 600; line-height: 1.2;
            margin-bottom: 1.5mm;
            display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical;
            overflow: hidden;
        }
        .label__code { font-size: 6.5pt; font-family: "Courier New", monospace; letter-spacing: .05em; margin-top: 1mm; }
        .label__price { font-size: 10pt; font-weight: 700; margin-top: 1mm; }
        .label__row { display: flex; align-items: center; justify-content: center; gap: 2mm; width: 100%; }
        .label svg { max-width: 100%; height: auto; }
        .label__qr { width: 13mm; flex: 0 0 13mm; }

        @media print {
            body { background: #fff; }
            .bar { display: none; }
            .sheet { margin: 0; width: auto; padding: 0; }
            .label { border-color: #e2e8f0; }
            @page { size: A4; margin: 8mm; }
        }
    </style>
</head>
<body>

<div class="bar">
    <div>
        <h1>Label Barcode Produk</h1>
        <p>{{ count($labels) }} label · atur jumlah salinan lewat parameter <code>?copies=</code> pada URL</p>
    </div>
    <button type="button" onclick="window.print()">Cetak Sekarang</button>
</div>

<div class="sheet">
    @forelse ($labels as $product)
        @php
            $type = $product->barcode_type ?: 'C128';
            $value = (string) ($product->barcode_value ?: $product->sku);
            $renderable = $codes->isRenderable($value, $type);
        @endphp

        <div class="label">
            <div class="label__name">{{ $product->name }}</div>

            <div class="label__row">
                <div style="flex:1;min-width:0">
                    @if ($renderable)
                        {!! $codes->barcodeSvg($value, $type, 1, 26) !!}
                    @else
                        {!! $codes->barcodeSvg($value, 'C128', 1, 26) !!}
                    @endif
                </div>

                @if ($showQr)
                    <div class="label__qr">{!! $codes->qrSvg((string) ($product->qr_value ?: $product->sku), 60, 0) !!}</div>
                @endif
            </div>

            <div class="label__code">{{ $value }}</div>

            @if ($showPrice)
                <div class="label__price">{{ money($product->price) }}</div>
            @endif
        </div>
    @empty
        <p style="grid-column:1/-1;text-align:center;color:#64748b;padding:40px">
            Tidak ada produk untuk dicetak.
        </p>
    @endforelse
</div>

</body>
</html>
