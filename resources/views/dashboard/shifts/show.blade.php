@extends('layouts.app')

@section('title', 'Shift #' . $shift->id)
@section('subtitle', $shift->user?->name . ' · ' . $shift->opened_at->translatedFormat('l, d F Y'))

@section('content')

<div class="page-head">
    <div class="row g-12">
        <a href="{{ route('admin.shifts.index') }}" class="btn btn--ghost btn--icon"><x-icon name="arrow-left" size="18"/></a>
        <div>
            <h1>Shift #{{ $shift->id }}</h1>
            <div class="row g-8 mt-4">
                <span class="badge badge--{{ $shift->isOpen() ? 'ok' : 'neutral' }}">
                    {{ $shift->isOpen() ? 'Sedang berjalan' : 'Ditutup' }}
                </span>
                <span class="small muted">{{ $shift->durationLabel() }}</span>
            </div>
        </div>
    </div>

    <a href="{{ route('admin.shifts.pdf', $shift) }}" class="btn btn--outline">
        <x-icon name="download" size="16"/> Unduh PDF
    </a>
</div>

<div class="grid grid-4 mb-20">
    <div class="stat stat--accent">
        <div class="stat__label">Total Penjualan</div>
        <div class="stat__value">{{ money($shift->total_sales) }}</div>
        <div class="stat__meta">{{ $shift->total_transactions }} transaksi</div>
    </div>
    <div class="stat">
        <div class="stat__label">Kas Seharusnya</div>
        <div class="stat__value">{{ money($shift->isOpen() ? $shift->expectedCashNow() : $shift->expected_cash) }}</div>
        <div class="stat__meta">Modal {{ money($shift->opening_cash) }} + tunai {{ money($shift->cash_sales) }}</div>
    </div>
    <div class="stat">
        <div class="stat__label">Kas Dihitung</div>
        <div class="stat__value">{{ $shift->counted_cash !== null ? money($shift->counted_cash) : '—' }}</div>
        <div class="stat__meta">{{ $shift->closed_at?->format('d/m/Y H:i') ?? 'Belum ditutup' }}</div>
    </div>
    <div class="stat">
        <div class="stat__label">Selisih Kas</div>
        <div class="stat__value {{ abs((float) $shift->cash_variance) < 0.01 ? 'ok' : ((float) $shift->cash_variance < 0 ? 'bad' : 'warn') }}">
            {{ $shift->closed_at ? money($shift->cash_variance) : '—' }}
        </div>
        <div class="stat__meta">
            @if (! $shift->closed_at) Menunggu penutupan
            @elseif (abs((float) $shift->cash_variance) < 0.01) Kas sesuai
            @elseif ((float) $shift->cash_variance > 0) Kas lebih
            @else Kas kurang @endif
        </div>
    </div>
</div>

<div class="grid grid-2-1">
    <div class="card">
        <div class="card__head"><div class="card__title">Transaksi pada Shift Ini</div></div>
        <div class="table-wrap">
            <table class="table table--compact">
                <thead>
                    <tr><th>Waktu</th><th>Invoice</th><th class="t-right">Item</th><th>Pembayaran</th><th class="t-right">Total</th><th>Status</th></tr>
                </thead>
                <tbody>
                    @forelse ($sales as $sale)
                        <tr>
                            <td class="small muted nowrap">{{ $sale->created_at->format('H:i') }}</td>
                            <td><a href="{{ route('admin.sales.show', $sale) }}" class="mono small semi">{{ $sale->invoice_number }}</a></td>
                            <td class="t-right num">{{ $sale->item_count }}</td>
                            <td class="small muted">{{ $sale->paymentLabel() }}</td>
                            <td class="t-right num semi">{{ money($sale->total) }}</td>
                            <td>
                                <span class="badge badge--{{ $sale->status === 'completed' ? 'ok' : 'bad' }}">
                                    {{ $sale->status === 'completed' ? 'Selesai' : 'Batal' }}
                                </span>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="6"><div class="empty"><div class="empty__title">Belum ada transaksi pada shift ini</div></div></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="card">
        <div class="card__head"><div class="card__title">Catatan Shift</div></div>
        <div class="card__body">
            <div class="row between small"><span class="muted">Dibuka</span>
                <span class="semi">{{ $shift->opened_at->format('d/m/Y H:i') }}</span></div>
            <div class="row between small mt-8"><span class="muted">Ditutup</span>
                <span class="semi">{{ $shift->closed_at?->format('d/m/Y H:i') ?? '—' }}</span></div>
            <div class="row between small mt-8"><span class="muted">Operator</span>
                <span class="semi">{{ $shift->user?->name }}</span></div>
            <div class="row between small mt-8"><span class="muted">Ditutup oleh</span>
                <span class="semi">{{ $shift->closedBy?->name ?? '—' }}</span></div>

            @if ($shift->opening_note)
                <div class="divider"></div>
                <div class="tiny subtle upper mb-4">Catatan Pembukaan</div>
                <div class="small muted">{{ $shift->opening_note }}</div>
            @endif

            @if ($shift->closing_note)
                <div class="divider"></div>
                <div class="tiny subtle upper mb-4">Catatan Penutupan</div>
                <div class="small muted">{{ $shift->closing_note }}</div>
            @endif
        </div>
    </div>
</div>

@endsection
