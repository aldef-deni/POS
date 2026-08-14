@php
    /**
     * Premium thermal receipt.
     *
     * Sized in millimetres so it prints 1:1 on a 58 mm or 80 mm roll, with a
     * screen preview that still looks like a real slip. Everything is inline
     * because the print window is opened standalone from the terminal.
     */
    $paper = $tenant?->receipt_paper === '58mm' ? 58 : 80;
    $contentWidth = $paper - 8;
    $autoPrint = $autoPrint ?? false;
    $reprint = $reprint ?? false;
@endphp
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Struk {{ $sale->invoice_number }}</title>
    <style>
        * { box-sizing: border-box; }

        html, body {
            margin: 0;
            padding: 0;
            background: #eef1f6;
            font-family: "Segoe UI", "Helvetica Neue", Arial, sans-serif;
            color: #111;
        }

        .toolbar {
            padding: 14px 18px;
            background: #fff;
            border-bottom: 1px solid #e2e8f0;
            display: flex; align-items: center; justify-content: space-between; gap: 12px;
            position: sticky; top: 0; z-index: 5;
        }
        .toolbar h1 { font-size: 14px; margin: 0; }
        .toolbar p { margin: 2px 0 0; font-size: 12px; color: #64748b; }
        .toolbar .actions { display: flex; gap: 8px; }
        .toolbar button, .toolbar a {
            padding: 9px 16px; border: 0; border-radius: 8px; cursor: pointer;
            font-size: 13px; font-weight: 600; text-decoration: none; display: inline-block;
        }
        .btn-print { background: #4f46e5; color: #fff; }
        .btn-back { background: #f1f5f9; color: #334155; }

        .paper {
            width: {{ $paper }}mm;
            margin: 18px auto;
            padding: 5mm 4mm 7mm;
            background: #fff;
            box-shadow: 0 8px 28px rgba(15, 23, 42, .12);
            font-size: 10.5px;
            line-height: 1.45;
        }

        .center { text-align: center; }
        .right  { text-align: right; }
        .bold   { font-weight: 700; }
        .muted  { color: #555; }
        .mono   { font-family: "Cascadia Mono", Consolas, "Courier New", monospace; }

        .logo { max-width: 32mm; max-height: 16mm; margin-bottom: 2mm; }

        .store-name {
            font-size: 15px; font-weight: 800;
            letter-spacing: .06em; text-transform: uppercase;
            line-height: 1.2;
        }
        .branch-name {
            font-size: 10.5px; font-weight: 700;
            letter-spacing: .04em; margin-top: .8mm;
        }
        .store-meta { font-size: 9px; color: #444; margin-top: 1mm; line-height: 1.4; }

        .rule       { border-top: 1px dashed #999; margin: 2.5mm 0; }
        .rule-solid { border-top: 1.5px solid #111; margin: 2mm 0; }

        .meta { width: 100%; font-size: 9.5px; }
        .meta td { padding: .4mm 0; vertical-align: top; }
        .meta td:first-child { color: #555; width: 17mm; }

        .items { width: 100%; border-collapse: collapse; }
        .items .name { font-weight: 600; font-size: 10.5px; padding-top: 1.4mm; }
        .items .calc { font-size: 9.5px; color: #444; }
        .items .amt  { text-align: right; font-weight: 600; white-space: nowrap; }
        .items .disc { font-size: 9px; color: #047857; }

        .totals { width: 100%; font-size: 10.5px; }
        .totals td { padding: .5mm 0; }
        .totals .lbl { color: #444; }
        .totals .val { text-align: right; font-weight: 600; white-space: nowrap; }
        .totals .grand td { font-size: 14px; font-weight: 800; padding-top: 1.5mm; }

        .pay { width: 100%; font-size: 10px; }
        .pay td { padding: .4mm 0; }
        .pay .val { text-align: right; font-weight: 600; }

        .change-box {
            border: 1.5px solid #111;
            padding: 2mm;
            margin-top: 2mm;
            text-align: center;
        }
        .change-box .lbl { font-size: 9px; letter-spacing: .08em; text-transform: uppercase; color: #444; }
        .change-box .val { font-size: 15px; font-weight: 800; }

        .qr { margin-top: 3mm; }
        .qr img { width: 22mm; height: 22mm; }

        .barcode { margin-top: 2mm; }
        .barcode svg { max-width: 100%; height: 11mm; }

        .footer-note { font-size: 9px; color: #333; white-space: pre-line; margin-top: 2mm; line-height: 1.45; }
        .thanks { font-size: 11px; font-weight: 700; margin-top: 2mm; }

        .stamp {
            border: 2px solid #b91c1c; color: #b91c1c;
            font-weight: 800; font-size: 12px; letter-spacing: .12em;
            padding: 1.5mm; text-align: center; margin-bottom: 3mm;
            transform: rotate(-1.5deg);
        }

        .reprint-tag {
            font-size: 9px; letter-spacing: .1em; text-transform: uppercase;
            color: #b45309; border: 1px dashed #b45309; padding: 1mm; text-align: center; margin-bottom: 2mm;
        }

        @media print {
            html, body { background: #fff; }
            .toolbar { display: none !important; }
            .paper {
                width: auto; margin: 0; padding: 0 2mm;
                box-shadow: none;
            }
            @page { size: {{ $paper }}mm auto; margin: 3mm 0; }
        }
    </style>
</head>
<body>

<div class="toolbar">
    <div>
        <h1>Struk {{ $sale->invoice_number }}</h1>
        <p>{{ $paper }} mm · {{ $sale->created_at->translatedFormat('d F Y H:i') }}</p>
    </div>
    <div class="actions">
        <a href="javascript:window.close()" class="btn-back">Tutup</a>
        <button type="button" class="btn-print" onclick="window.print()">Cetak Struk</button>
    </div>
</div>

<div class="paper">

    @if ($sale->isVoided())
        <div class="stamp">TRANSAKSI DIBATALKAN</div>
    @endif

    @if ($reprint)
        <div class="reprint-tag">Cetak Ulang</div>
    @endif

    {{-- Store identity --}}
    <div class="center">
        @if ($tenant?->receipt_show_logo && $tenant?->logoUrl())
            <img src="{{ $tenant->logoUrl() }}" alt="" class="logo">
        @endif

        <div class="store-name">{{ $tenant?->name ?? config('app.name') }}</div>

        {{-- The branch that actually served the customer, with its own
             address and phone so a return goes to the right place. --}}
        @php $branch = $sale->outlet; @endphp

        @if ($branch)
            <div class="branch-name">{{ $branch->name }}</div>
        @endif

        <div class="store-meta">
            @php
                $address = $branch?->printableAddress() ?? $tenant?->address;
                $city = $branch?->city ?? $tenant?->city;
                $phone = $branch?->printablePhone() ?? $tenant?->phone;
            @endphp

            @if ($address){{ $address }}@endif
            @if ($city)<br>{{ $city }}@endif
            @if ($phone)<br>Telp. {{ $phone }}@endif
            @if ($tenant?->tax_number)<br>NPWP {{ $tenant->tax_number }}@endif
        </div>
    </div>

    <div class="rule-solid"></div>

    {{-- Transaction meta --}}
    <table class="meta">
        <tr><td>No.</td><td class="mono bold">{{ $sale->invoice_number }}</td></tr>
        <tr><td>Tanggal</td><td>{{ $sale->created_at->format('d/m/Y H:i:s') }}</td></tr>
        <tr><td>Kasir</td><td>{{ $sale->user?->name ?? '—' }}</td></tr>
        <tr><td>Pelanggan</td><td>{{ $sale->customer?->name ?? 'Umum' }}</td></tr>
    </table>

    <div class="rule"></div>

    {{-- Line items --}}
    <table class="items">
        @foreach ($sale->items as $item)
            <tr>
                <td colspan="2" class="name">{{ $item->name }}</td>
            </tr>
            <tr>
                <td class="calc">
                    {{ qty_label($item->qty) }} {{ $item->unit }} × {{ money($item->unit_price, false) }}
                </td>
                <td class="amt">{{ money((float) $item->unit_price * (float) $item->qty, false) }}</td>
            </tr>
            @if ((float) $item->discount_amount > 0)
                <tr>
                    <td class="disc">Diskon item</td>
                    <td class="amt disc">− {{ money($item->discount_amount, false) }}</td>
                </tr>
            @endif
        @endforeach
    </table>

    <div class="rule"></div>

    {{-- Totals --}}
    <table class="totals">
        <tr>
            <td class="lbl">Subtotal ({{ qty_label($sale->total_qty) }} item)</td>
            <td class="val">{{ money($sale->subtotal, false) }}</td>
        </tr>

        @if ((float) $sale->discount_amount > 0)
            <tr>
                <td class="lbl">Diskon</td>
                <td class="val">− {{ money($sale->discount_amount, false) }}</td>
            </tr>
        @endif

        @if ((float) $sale->service_charge_amount > 0)
            <tr>
                <td class="lbl">Biaya layanan</td>
                <td class="val">{{ money($sale->service_charge_amount, false) }}</td>
            </tr>
        @endif

        @if ((float) $sale->tax_amount > 0)
            <tr>
                <td class="lbl">
                    PPN {{ rtrim(rtrim((string) $sale->tax_percent, '0'), '.') }}%
                    @if ($tenant?->tax_inclusive) (termasuk) @endif
                </td>
                <td class="val">{{ money($sale->tax_amount, false) }}</td>
            </tr>
        @endif

        @if (abs((float) $sale->rounding_amount) > 0.004)
            <tr>
                <td class="lbl">Pembulatan</td>
                <td class="val">{{ money($sale->rounding_amount, false) }}</td>
            </tr>
        @endif
    </table>

    <div class="rule-solid"></div>

    <table class="totals">
        <tr class="grand">
            <td>TOTAL</td>
            <td class="val">{{ money($sale->total) }}</td>
        </tr>
    </table>

    <div class="rule"></div>

    {{-- Tenders --}}
    <table class="pay">
        @foreach ($sale->payments as $payment)
            <tr>
                <td>{{ $payment->methodLabel() }}</td>
                <td class="val">{{ money($payment->amount, false) }}</td>
            </tr>
        @endforeach
    </table>

    @if ((float) $sale->change_amount > 0)
        <div class="change-box">
            <div class="lbl">Kembalian</div>
            <div class="val">{{ money($sale->change_amount) }}</div>
        </div>
    @endif

    {{-- Verification marks --}}
    <div class="center">
        @if ($tenant?->receipt_show_qr)
            <div class="qr">
                <img src="{{ $qrDataUri }}" alt="QR verifikasi">
                <div style="font-size:8px;color:#666;margin-top:1mm">Pindai untuk verifikasi transaksi</div>
            </div>
        @endif

        <div class="rule"></div>

        @if ($tenant?->receipt_header)
            <div class="thanks">{{ $tenant->receipt_header }}</div>
        @endif

        @php $footer = $sale->outlet?->printableFooter() ?? $tenant?->receipt_footer; @endphp

        @if ($footer)
            <div class="footer-note">{{ $footer }}</div>
        @endif

        <div style="font-size:8px;color:#888;margin-top:3mm">
            {{ $sale->invoice_number }} · dicetak {{ now()->format('d/m/Y H:i') }}
        </div>
    </div>
</div>

@if ($autoPrint)
    <script>
        // Give the QR image a moment to decode before the print dialog opens.
        window.addEventListener('load', function () {
            setTimeout(function () { window.print(); }, 350);
        });
    </script>
@endif

</body>
</html>
