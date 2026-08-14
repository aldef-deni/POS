<!DOCTYPE html>
<html lang="id" data-theme="light">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Laporan Shift #{{ $shift->id }}</title>
    <link rel="stylesheet" href="{{ asset_v('assets/css/app.css') }}">
    <link rel="stylesheet" href="{{ asset_v('assets/css/pos.css') }}">
    <style>
        @media print {
            .no-print { display: none !important; }
            .content { padding: 0; }
            body { background: #fff; }
        }
    </style>
</head>
<body @if(session('status')) data-flash="{{ session('status') }}" data-flash-type="ok" @endif>

<header class="pos-top no-print">
    <a href="{{ route('pos.index') }}" class="btn btn--ghost btn--icon"><x-icon name="arrow-left" size="18"/></a>

    <div class="grow">
        <div class="pos-top__name">Laporan Shift #{{ $shift->id }}</div>
        <div class="pos-top__meta">
            {{ $shift->user?->name }} · {{ $shift->opened_at->translatedFormat('d F Y') }}
        </div>
    </div>

    <button type="button" class="btn btn--outline btn--sm" onclick="window.print()">
        <x-icon name="printer" size="15"/> Cetak
    </button>

    @if ($shift->isOpen())
        <a href="{{ route('pos.index') }}" class="btn btn--primary btn--sm">Lanjut Berjualan</a>
    @else
        <form method="POST" action="{{ route('pos.logout') }}">
            @csrf
            <button type="submit" class="btn btn--primary btn--sm">
                <x-icon name="logout" size="15"/> Selesai &amp; Keluar
            </button>
        </form>
    @endif
</header>

<div class="content" style="max-width:900px">

    @if (! $shift->isOpen())
        <div class="alert alert--ok">
            <x-icon name="check-circle" size="17" class="alert__icon"/>
            <div>
                <div class="semi">Shift berhasil ditutup</div>
                <div class="small mt-4">
                    Terima kasih atas kerja kerasnya, {{ explode(' ', $shift->user?->name ?? '')[0] }}.
                    Simpan atau cetak laporan ini sebagai bukti serah terima kas.
                </div>
            </div>
        </div>
    @endif

    <div class="grid grid-4 mb-20">
        <div class="stat stat--accent">
            <div class="stat__label">Total Penjualan</div>
            <div class="stat__value" style="font-size:21px">{{ money($shift->total_sales) }}</div>
            <div class="stat__meta">{{ $shift->total_transactions }} transaksi</div>
        </div>
        <div class="stat">
            <div class="stat__label">Kas Seharusnya</div>
            <div class="stat__value" style="font-size:21px">
                {{ money($shift->closed_at ? $shift->expected_cash : $shift->expectedCashNow()) }}
            </div>
            <div class="stat__meta">Modal {{ money($shift->opening_cash) }}</div>
        </div>
        <div class="stat">
            <div class="stat__label">Kas Dihitung</div>
            <div class="stat__value" style="font-size:21px">
                {{ $shift->counted_cash !== null ? money($shift->counted_cash) : '—' }}
            </div>
            <div class="stat__meta">{{ $shift->closed_at?->format('d/m/Y H:i') ?? 'Belum ditutup' }}</div>
        </div>
        <div class="stat">
            <div class="stat__label">Selisih Kas</div>
            <div class="stat__value {{ abs((float) $shift->cash_variance) < 0.01 ? 'ok' : ((float) $shift->cash_variance < 0 ? 'bad' : 'warn') }}"
                 style="font-size:21px">
                {{ $shift->closed_at ? money($shift->cash_variance) : '—' }}
            </div>
            <div class="stat__meta">
                @if (! $shift->closed_at) Shift masih berjalan
                @elseif (abs((float) $shift->cash_variance) < 0.01) Kas sesuai catatan
                @elseif ((float) $shift->cash_variance > 0) Kas lebih dari catatan
                @else Kas kurang dari catatan @endif
            </div>
        </div>
    </div>

    <div class="grid grid-2 mb-20">
        <div class="card">
            <div class="card__head"><div class="card__title">Rincian Pembayaran</div></div>
            <div class="card__body">
                @forelse ($payments as $payment)
                    <div class="between" style="padding:9px 0;border-bottom:1px solid var(--border)">
                        <span class="small semi">
                            {{ \App\Models\SalePayment::methods()[$payment->method] ?? $payment->method }}
                        </span>
                        <span class="t-right">
                            <span class="num semi">{{ money($payment->amount) }}</span>
                            <div class="tiny subtle">{{ $payment->count }} transaksi</div>
                        </span>
                    </div>
                @empty
                    <p class="small muted">Belum ada pembayaran tercatat.</p>
                @endforelse
            </div>
        </div>

        <div class="card">
            <div class="card__head"><div class="card__title">Informasi Shift</div></div>
            <div class="card__body">
                <div class="row between small"><span class="muted">Operator</span>
                    <span class="semi">{{ $shift->user?->name }}</span></div>
                <div class="row between small mt-8"><span class="muted">Dibuka</span>
                    <span class="semi">{{ $shift->opened_at->format('d/m/Y H:i') }}</span></div>
                <div class="row between small mt-8"><span class="muted">Ditutup</span>
                    <span class="semi">{{ $shift->closed_at?->format('d/m/Y H:i') ?? '—' }}</span></div>
                <div class="row between small mt-8"><span class="muted">Durasi</span>
                    <span class="semi">{{ $shift->durationLabel() }}</span></div>

                @if ($shift->closing_note)
                    <div class="divider"></div>
                    <div class="tiny subtle upper mb-4">Catatan Penutupan</div>
                    <div class="small muted">{{ $shift->closing_note }}</div>
                @endif
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card__head">
            <div class="card__title">Transaksi ({{ $sales->count() }})</div>
        </div>
        <div class="table-wrap">
            <table class="table table--compact">
                <thead>
                    <tr><th>Waktu</th><th>Invoice</th><th class="t-right">Item</th><th class="t-right">Total</th></tr>
                </thead>
                <tbody>
                    @forelse ($sales as $sale)
                        <tr>
                            <td class="small muted">{{ $sale->created_at->format('H:i') }}</td>
                            <td class="mono small">{{ $sale->invoice_number }}</td>
                            <td class="t-right num">{{ $sale->item_count }}</td>
                            <td class="t-right num semi">{{ money($sale->total) }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="4"><div class="empty"><div class="empty__title">Tidak ada transaksi</div></div></td></tr>
                    @endforelse
                </tbody>
                @if ($sales->count())
                    <tfoot>
                        <tr>
                            <td colspan="2">Total</td>
                            <td class="t-right num">{{ $sales->sum('item_count') }}</td>
                            <td class="t-right num">{{ money($sales->sum('total')) }}</td>
                        </tr>
                    </tfoot>
                @endif
            </table>
        </div>
    </div>
</div>

<script src="{{ asset_v('assets/js/app.js') }}"></script>
</body>
</html>
