<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>Invoice {{ $sale->invoice_number }}</title>
    <style>
        @page { margin: 34px; }

        body {
            font-family: "DejaVu Sans", sans-serif;
            font-size: 9.5pt;
            color: #1e293b;
            margin: 0;
        }

        table { width: 100%; border-collapse: collapse; }

        .head td { vertical-align: top; padding: 0; }
        .brand { font-size: 17pt; font-weight: bold; color: #0f172a; letter-spacing: -.02em; }
        .brand-sub { font-size: 8pt; color: #64748b; line-height: 1.5; margin-top: 3px; }

        .doc-label {
            font-size: 8pt; letter-spacing: .12em; text-transform: uppercase;
            color: #4f46e5; font-weight: bold;
        }
        .doc-no { font-size: 13pt; font-weight: bold; color: #0f172a; margin-top: 2px; }

        .accent { height: 3px; background: #4f46e5; margin: 12px 0 16px; }

        .info td {
            border: 1px solid #e2e8f0;
            padding: 8px 10px;
            width: 25%;
            vertical-align: top;
        }
        .info .label { font-size: 6.8pt; text-transform: uppercase; letter-spacing: .05em; color: #64748b; }
        .info .value { font-size: 9.5pt; font-weight: bold; color: #0f172a; padding-top: 2px; }

        .items { margin-top: 16px; }
        .items thead th {
            background: #0f172a; color: #fff;
            padding: 7px 9px;
            font-size: 7.6pt; text-transform: uppercase; letter-spacing: .05em;
            text-align: left;
        }
        .items tbody td { padding: 7px 9px; border-bottom: 1px solid #eef2f7; font-size: 8.8pt; }
        .items tbody tr:nth-child(even) td { background: #fafbfd; }
        .num { text-align: right; }

        .totals { margin-top: 14px; }
        .totals td { padding: 4px 9px; font-size: 9.5pt; }
        .totals .lbl { text-align: right; color: #475569; }
        .totals .val { text-align: right; font-weight: bold; width: 130px; }
        .totals .grand td {
            border-top: 2px solid #0f172a;
            font-size: 13pt; font-weight: bold; color: #0f172a;
            padding-top: 8px;
        }

        .pay-box { border: 1px solid #e2e8f0; padding: 10px 12px; }
        .pay-box .title { font-size: 7pt; text-transform: uppercase; letter-spacing: .05em; color: #64748b; margin-bottom: 5px; }
        .pay-box td { padding: 2px 0; font-size: 8.8pt; }

        .void {
            border: 2px solid #b91c1c; color: #b91c1c;
            padding: 8px; text-align: center; font-weight: bold; letter-spacing: .1em;
            margin-bottom: 14px; font-size: 11pt;
        }

        .foot {
            margin-top: 22px; padding-top: 10px;
            border-top: 1px solid #e2e8f0;
            font-size: 7.6pt; color: #94a3b8;
        }
    </style>
</head>
<body>

@if ($sale->isVoided())
    <div class="void">TRANSAKSI DIBATALKAN — {{ $sale->void_reason }}</div>
@endif

<table class="head">
    <tr>
        <td>
            <div class="brand">{{ $tenant?->name ?? config('app.name') }}</div>
            <div class="brand-sub">
                {{ $tenant?->legal_name }}<br>
                {{ $tenant?->address }}{{ $tenant?->city ? ', '.$tenant->city : '' }}<br>
                @if ($tenant?->phone) Telp. {{ $tenant->phone }} @endif
                @if ($tenant?->email) · {{ $tenant->email }} @endif
                @if ($tenant?->tax_number) <br>NPWP {{ $tenant->tax_number }} @endif
            </div>
        </td>
        <td style="text-align:right;width:210px">
            <div class="doc-label">Invoice Penjualan</div>
            <div class="doc-no">{{ $sale->invoice_number }}</div>
            <img src="{{ $qrDataUri }}" alt="" style="width:70px;height:70px;margin-top:8px">
        </td>
    </tr>
</table>

<div class="accent"></div>

<table class="info">
    <tr>
        <td>
            <div class="label">Tanggal</div>
            <div class="value">{{ $sale->created_at->format('d/m/Y H:i') }}</div>
        </td>
        <td>
            <div class="label">Kasir</div>
            <div class="value">{{ $sale->user?->name ?? '—' }}</div>
        </td>
        <td>
            <div class="label">Pelanggan</div>
            <div class="value">{{ $sale->customer?->name ?? 'Pelanggan Umum' }}</div>
        </td>
        <td>
            <div class="label">Status</div>
            <div class="value">{{ $sale->status === 'completed' ? 'Lunas' : 'Dibatalkan' }}</div>
        </td>
    </tr>
</table>

<table class="items">
    <thead>
        <tr>
            <th style="width:26px">#</th>
            <th>Produk</th>
            <th style="width:80px">SKU</th>
            <th class="num" style="width:56px">Qty</th>
            <th class="num" style="width:86px">Harga</th>
            <th class="num" style="width:76px">Diskon</th>
            <th class="num" style="width:92px">Jumlah</th>
        </tr>
    </thead>
    <tbody>
        @foreach ($sale->items as $index => $item)
            <tr>
                <td>{{ $index + 1 }}</td>
                <td>{{ $item->name }}</td>
                <td style="font-size:7.8pt;color:#64748b">{{ $item->sku }}</td>
                <td class="num">{{ qty_label($item->qty) }} {{ $item->unit }}</td>
                <td class="num">{{ money($item->unit_price, false) }}</td>
                <td class="num">{{ (float) $item->discount_amount > 0 ? money($item->discount_amount, false) : '—' }}</td>
                <td class="num">{{ money($item->line_total, false) }}</td>
            </tr>
        @endforeach
    </tbody>
</table>

<table class="totals">
    <tr>
        <td style="vertical-align:top">
            <div class="pay-box">
                <div class="title">Rincian Pembayaran</div>
                <table>
                    @foreach ($sale->payments as $payment)
                        <tr>
                            <td>{{ $payment->methodLabel() }}</td>
                            <td class="num">{{ money($payment->amount, false) }}</td>
                        </tr>
                    @endforeach
                    <tr>
                        <td style="padding-top:5px;border-top:1px solid #e2e8f0">Kembalian</td>
                        <td class="num" style="padding-top:5px;border-top:1px solid #e2e8f0">
                            {{ money($sale->change_amount, false) }}
                        </td>
                    </tr>
                </table>
            </div>
        </td>
        <td style="width:290px;vertical-align:top">
            <table>
                <tr>
                    <td class="lbl">Subtotal</td>
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
                        <td class="lbl">PPN {{ rtrim(rtrim((string) $sale->tax_percent, '0'), '.') }}%</td>
                        <td class="val">{{ money($sale->tax_amount, false) }}</td>
                    </tr>
                @endif
                @if (abs((float) $sale->rounding_amount) > 0.004)
                    <tr>
                        <td class="lbl">Pembulatan</td>
                        <td class="val">{{ money($sale->rounding_amount, false) }}</td>
                    </tr>
                @endif
                <tr class="grand">
                    <td class="lbl">TOTAL</td>
                    <td class="val">{{ money($sale->total) }}</td>
                </tr>
            </table>
        </td>
    </tr>
</table>

<div class="foot">
    Dokumen ini dibuat otomatis oleh sistem dan sah tanpa tanda tangan.
    @if ($tenant?->receipt_footer) {{ str_replace("\n", ' ', $tenant->receipt_footer) }} @endif
    · Dicetak {{ now()->format('d/m/Y H:i') }}
</div>

</body>
</html>
