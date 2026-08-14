@extends('layouts.app')

@section('title', 'Penjualan')
@section('subtitle', 'Riwayat seluruh transaksi kasir')

@section('content')

<div class="page-head">
    <div>
        <h1>Penjualan</h1>
        <p class="muted mt-4">
            {{ $sales->total() }} transaksi · total selesai {{ money($periodTotal) }}
        </p>
    </div>
</div>

<div class="card mb-20">
    <div class="card__body card__body--tight">
        <form method="GET" class="row g-8 wrap" data-auto-submit>
            <input type="date" name="from" value="{{ $from }}" class="input" style="width:auto">
            <span class="muted small">s/d</span>
            <input type="date" name="to" value="{{ $to }}" class="input" style="width:auto">

            <select name="cashier" class="select" style="width:auto;min-width:160px">
                <option value="">Semua kasir</option>
                @foreach ($cashiers as $cashier)
                    <option value="{{ $cashier->id }}" @selected(($filters['cashier'] ?? '') == $cashier->id)>
                        {{ $cashier->name }}
                    </option>
                @endforeach
            </select>

            <select name="status" class="select" style="width:auto;min-width:140px">
                <option value="">Semua status</option>
                <option value="completed" @selected(($filters['status'] ?? '') === 'completed')>Selesai</option>
                <option value="voided" @selected(($filters['status'] ?? '') === 'voided')>Dibatalkan</option>
            </select>

            <div class="search grow" style="min-width:180px">
                <x-icon name="search" size="16" class="search__icon"/>
                <input type="text" name="q" value="{{ $filters['q'] ?? '' }}" class="input" placeholder="No. invoice…">
            </div>

            <button type="submit" class="btn btn--outline"><x-icon name="filter" size="15"/> Filter</button>
        </form>
    </div>
</div>

<div class="card">
    <div class="table-wrap">
        <table class="table">
            <thead>
                <tr>
                    <th>Invoice</th><th>Waktu</th><th>Kasir</th><th>Pelanggan</th>
                    <th class="t-right">Item</th><th>Pembayaran</th>
                    <th class="t-right">Diskon</th><th class="t-right">Total</th>
                    <th>Status</th><th></th>
                </tr>
            </thead>
            <tbody>
                @forelse ($sales as $sale)
                    <tr>
                        <td>
                            <a href="{{ route('admin.sales.show', $sale) }}" class="mono semi">{{ $sale->invoice_number }}</a>
                        </td>
                        <td class="small muted nowrap">{{ $sale->created_at->format('d/m/y H:i') }}</td>
                        <td class="small">{{ $sale->user?->name ?? '—' }}</td>
                        <td class="small muted">{{ $sale->customer?->name ?? 'Umum' }}</td>
                        <td class="t-right num">{{ $sale->item_count }}</td>
                        <td class="small">
                            @foreach ($sale->payments as $payment)
                                <span class="badge badge--neutral">{{ $payment->methodLabel() }}</span>
                            @endforeach
                        </td>
                        <td class="t-right num {{ (float) $sale->discount_amount > 0 ? 'ok' : 'subtle' }}">
                            {{ (float) $sale->discount_amount > 0 ? money($sale->discount_amount) : '—' }}
                        </td>
                        <td class="t-right num semi">{{ money($sale->total) }}</td>
                        <td>
                            <span class="badge badge--{{ $sale->status === 'completed' ? 'ok' : 'bad' }}">
                                {{ $sale->status === 'completed' ? 'Selesai' : 'Dibatalkan' }}
                            </span>
                        </td>
                        <td class="t-right">
                            <div class="dropdown">
                                <button type="button" class="btn btn--ghost btn--icon" data-dropdown>⋯</button>
                                <div class="dropdown__menu">
                                    <a href="{{ route('admin.sales.show', $sale) }}" class="dropdown__item">
                                        <x-icon name="receipt" size="15"/> Detail
                                    </a>
                                    <a href="{{ route('admin.sales.receipt', $sale) }}" target="_blank" class="dropdown__item">
                                        <x-icon name="printer" size="15"/> Cetak struk
                                    </a>
                                    <a href="{{ route('admin.sales.invoice', $sale) }}" class="dropdown__item">
                                        <x-icon name="download" size="15"/> Invoice PDF
                                    </a>
                                </div>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="10">
                        <div class="empty">
                            <div class="empty__icon"><x-icon name="receipt" size="24"/></div>
                            <div class="empty__title">Tidak ada transaksi</div>
                            <div class="empty__text">Coba ubah rentang tanggal atau filter yang dipakai.</div>
                        </div>
                    </td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{ $sales->links() }}
</div>

@endsection
