<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>Laporan Shift #{{ $shift->id }}</title>
    <style>
        @page { margin: 34px; }
        body { font-family: "DejaVu Sans", sans-serif; font-size: 9.5pt; color: #1e293b; margin: 0; }
        table { width: 100%; border-collapse: collapse; }

        .brand { font-size: 16pt; font-weight: bold; color: #0f172a; }
        .brand-sub { font-size: 8pt; color: #64748b; margin-top: 3px; }
        .doc-label { font-size: 8pt; letter-spacing: .12em; text-transform: uppercase; color: #4f46e5; font-weight: bold; }
        .doc-no { font-size: 13pt; font-weight: bold; margin-top: 2px; }
        .accent { height: 3px; background: #4f46e5; margin: 12px 0 16px; }

        .cards td { border: 1px solid #e2e8f0; padding: 9px 11px; width: 25%; vertical-align: top; }
        .cards .label { font-size: 6.8pt; text-transform: uppercase; letter-spacing: .05em; color: #64748b; }
        .cards .value { font-size: 12pt; font-weight: bold; color: #0f172a; padding-top: 3px; }

        .section { font-size: 10pt; font-weight: bold; margin: 18px 0 8px; color: #0f172a; }

        .recon td { padding: 5px 9px; border-bottom: 1px solid #eef2f7; font-size: 9pt; }
        .recon .num { text-align: right; font-weight: bold; }
        .recon .total td { border-top: 2px solid #0f172a; border-bottom: 0; font-size: 11pt; font-weight: bold; padding-top: 8px; }

        .data thead th {
            background: #f1f5f9; border-bottom: 1.5px solid #cbd5e1;
            padding: 6px 8px; font-size: 7.4pt; text-transform: uppercase;
            letter-spacing: .04em; color: #475569; text-align: left;
        }
        .data tbody td { padding: 5px 8px; border-bottom: 1px solid #eef2f7; font-size: 8.4pt; }
        .num { text-align: right; }

        .variance-ok  { color: #047857; }
        .variance-bad { color: #b91c1c; }

        .sign { margin-top: 30px; }
        .sign td { width: 50%; text-align: center; font-size: 8.5pt; color: #64748b; padding-top: 6px; }
        .sign .line { border-top: 1px solid #94a3b8; margin: 42px 26px 6px; }

        .foot { margin-top: 20px; padding-top: 9px; border-top: 1px solid #e2e8f0; font-size: 7.6pt; color: #94a3b8; }
    </style>
</head>
<body>

<table>
    <tr>
        <td style="vertical-align:top">
            <div class="brand">{{ $tenant?->name ?? config('app.name') }}</div>
            <div class="brand-sub">
                {{ $tenant?->address }}{{ $tenant?->city ? ', '.$tenant->city : '' }}
                @if ($tenant?->phone) · {{ $tenant->phone }} @endif
            </div>
        </td>
        <td style="text-align:right;vertical-align:top">
            <div class="doc-label">Laporan Tutup Shift</div>
            <div class="doc-no">Shift #{{ $shift->id }}</div>
            <div class="brand-sub">
                <b>{{ $shift->outlet?->name ?? '—' }}</b><br>
                {{ $shift->opened_at->translatedFormat('d F Y') }}
            </div>
        </td>
    </tr>
</table>

<div class="accent"></div>

<table class="cards">
    <tr>
        <td>
            <div class="label">Kasir</div>
            <div class="value" style="font-size:10pt">{{ $shift->user?->name ?? '—' }}</div>
        </td>
        <td>
            <div class="label">Dibuka</div>
            <div class="value" style="font-size:10pt">{{ $shift->opened_at->format('d/m/Y H:i') }}</div>
        </td>
        <td>
            <div class="label">Ditutup</div>
            <div class="value" style="font-size:10pt">{{ $shift->closed_at?->format('d/m/Y H:i') ?? 'Masih terbuka' }}</div>
        </td>
        <td>
            <div class="label">Durasi</div>
            <div class="value" style="font-size:10pt">{{ $shift->durationLabel() }}</div>
        </td>
    </tr>
</table>

<div class="section">Rekonsiliasi Kas</div>

<table class="recon">
    <tr>
        <td>Modal awal laci</td>
        <td class="num">{{ money($shift->opening_cash) }}</td>
    </tr>
    <tr>
        <td>Penjualan tunai (bersih setelah kembalian)</td>
        <td class="num">{{ money($shift->cash_sales) }}</td>
    </tr>
    <tr class="total">
        <td>Kas seharusnya ada di laci</td>
        <td class="num">{{ money($shift->closed_at ? $shift->expected_cash : $shift->expectedCashNow()) }}</td>
    </tr>
    <tr>
        <td style="padding-top:10px">Kas dihitung fisik</td>
        <td class="num" style="padding-top:10px">
            {{ $shift->counted_cash !== null ? money($shift->counted_cash) : '—' }}
        </td>
    </tr>
    <tr class="total">
        <td>Selisih kas</td>
        <td class="num {{ abs((float) $shift->cash_variance) < 0.01 ? 'variance-ok' : 'variance-bad' }}">
            {{ $shift->closed_at ? money($shift->cash_variance) : '—' }}
            @if ($shift->closed_at)
                @if (abs((float) $shift->cash_variance) < 0.01) (sesuai)
                @elseif ((float) $shift->cash_variance > 0) (lebih)
                @else (kurang) @endif
            @endif
        </td>
    </tr>
</table>

<div class="section">Ringkasan Penjualan</div>

<table class="cards">
    <tr>
        <td>
            <div class="label">Total Penjualan</div>
            <div class="value">{{ money($shift->total_sales) }}</div>
        </td>
        <td>
            <div class="label">Jumlah Transaksi</div>
            <div class="value">{{ $shift->total_transactions }}</div>
        </td>
        <td>
            <div class="label">Tunai</div>
            <div class="value">{{ money($shift->cash_sales) }}</div>
        </td>
        <td>
            <div class="label">Non-Tunai</div>
            <div class="value">{{ money($shift->non_cash_sales) }}</div>
        </td>
    </tr>
</table>

<div class="section">Daftar Transaksi ({{ $sales->count() }})</div>

@if ($sales->count())
    <table class="data">
        <thead>
            <tr>
                <th style="width:52px">Waktu</th>
                <th>No. Invoice</th>
                <th class="num" style="width:44px">Item</th>
                <th style="width:120px">Pembayaran</th>
                <th class="num" style="width:90px">Total</th>
                <th style="width:66px">Status</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($sales as $sale)
                <tr>
                    <td>{{ $sale->created_at->format('H:i') }}</td>
                    <td>{{ $sale->invoice_number }}</td>
                    <td class="num">{{ $sale->item_count }}</td>
                    <td>{{ $sale->paymentLabel() }}</td>
                    <td class="num">{{ money($sale->total, false) }}</td>
                    <td>{{ $sale->status === 'completed' ? 'Selesai' : 'Batal' }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
@else
    <p style="color:#94a3b8;font-size:9pt">Tidak ada transaksi pada shift ini.</p>
@endif

@if ($shift->closing_note)
    <div class="section">Catatan Penutupan</div>
    <p style="font-size:9pt;color:#475569">{{ $shift->closing_note }}</p>
@endif

<table class="sign">
    <tr>
        <td>
            <div class="line"></div>
            Kasir<br><b>{{ $shift->user?->name }}</b>
        </td>
        <td>
            <div class="line"></div>
            Supervisor / Owner<br><b>{{ $shift->closedBy?->name ?? '—' }}</b>
        </td>
    </tr>
</table>

<div class="foot">
    Dicetak {{ now()->translatedFormat('d F Y H:i') }} · {{ $tenant?->name }} · Dokumen internal
</div>

</body>
</html>
