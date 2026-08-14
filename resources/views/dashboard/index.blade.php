@extends('layouts.app')

@section('title', 'Dashboard')
@section('subtitle', 'Ikhtisar performa toko ' . $tenant?->name)

@section('content')

@php
    $c = $comparison;
    $delta = function (float $value) {
        $dir = $value > 0.5 ? 'up' : ($value < -0.5 ? 'down' : 'flat');
        return [$dir, ($value > 0 ? '+' : '') . number_format($value, 1, ',', '.') . '%'];
    };
@endphp

{{-- Date range --}}
<div class="page-head">
    <div>
        <h1>Selamat datang, {{ explode(' ', auth('web')->user()->name)[0] }}</h1>
        <p class="muted mt-4">
            <span class="badge badge--{{ $outlet ? 'neutral' : 'violet' }}">
                <x-icon name="{{ $outlet ? 'store' : 'layers' }}" size="11"/>
                {{ $outlet?->name ?? 'Semua Outlet' }}
            </span>
            · Ringkasan periode {{ \Carbon\Carbon::parse($from)->translatedFormat('d M Y') }}
            – {{ \Carbon\Carbon::parse($to)->translatedFormat('d M Y') }}
        </p>
    </div>

    <form method="GET" class="row g-8 wrap" data-auto-submit>
        <input type="date" name="from" value="{{ $from }}" class="input" style="width:auto">
        <span class="muted small">s/d</span>
        <input type="date" name="to" value="{{ $to }}" class="input" style="width:auto">
        <button class="btn btn--outline btn--sm" type="submit"><x-icon name="filter" size="15"/> Terapkan</button>
    </form>
</div>

{{-- Today at a glance --}}
<div class="grid grid-4 mb-20">
    <div class="stat stat--accent">
        <div class="stat__label">Omzet Hari Ini</div>
        <div class="stat__value">{{ money($todaySummary['revenue']) }}</div>
        <div class="stat__meta">{{ $todaySummary['transactions'] }} transaksi · rata-rata {{ money($todaySummary['average_basket']) }}</div>
        <div class="stat__icon"><x-icon name="wallet" size="18"/></div>
    </div>

    @php [$dir, $label] = $delta($c['revenue_delta']); @endphp
    <div class="stat">
        <div class="stat__label">Omzet Periode</div>
        <div class="stat__value">{{ money($c['current']['revenue']) }}</div>
        <div class="stat__meta">
            <span class="delta delta--{{ $dir }}">
                <x-icon name="{{ $dir === 'down' ? 'trending-down' : 'trending-up' }}" size="13"/>
                {{ $label }}
            </span>
            vs periode sebelumnya
        </div>
        <div class="stat__icon"><x-icon name="trending-up" size="18"/></div>
    </div>

    @php [$dir, $label] = $delta($c['transactions_delta']); @endphp
    <div class="stat">
        <div class="stat__label">Transaksi</div>
        <div class="stat__value">{{ number_format($c['current']['transactions'], 0, ',', '.') }}</div>
        <div class="stat__meta">
            <span class="delta delta--{{ $dir }}">{{ $label }}</span>
            · {{ qty_label($c['current']['qty']) }} item terjual
        </div>
        <div class="stat__icon"><x-icon name="receipt" size="18"/></div>
    </div>

    @allow('report.profit')
        @php [$dir, $label] = $delta($c['profit_delta']); @endphp
        <div class="stat">
            <div class="stat__label">Laba Kotor</div>
            <div class="stat__value">{{ money($c['current']['profit']) }}</div>
            <div class="stat__meta">
                <span class="delta delta--{{ $dir }}">{{ $label }}</span>
                · margin {{ percent_label($c['current']['margin_percent']) }}
            </div>
            <div class="stat__icon"><x-icon name="chart" size="18"/></div>
        </div>
    @endallow
</div>

{{-- Branch comparison, only while looking at the whole chain --}}
@if ($outletPerformance->isNotEmpty())
    <div class="card mb-20">
        <div class="card__head">
            <div>
                <div class="card__title">Performa per Outlet</div>
                <div class="card__sub">Kontribusi setiap cabang pada periode ini</div>
            </div>
            @allow('outlet.manage')
                <a href="{{ route('admin.outlets.index') }}" class="btn btn--ghost btn--sm">
                    Kelola outlet <x-icon name="chevron-right" size="14"/>
                </a>
            @endallow
        </div>
        <div class="card__body">
            @php $maxRevenue = $outletPerformance->max('revenue') ?: 1; @endphp

            @foreach ($outletPerformance as $row)
                <div class="mb-16">
                    <div class="between mb-4">
                        <span class="small semi">
                            <span class="code-chip" style="font-size:10px;padding:1px 6px">{{ $row['code'] }}</span>
                            {{ $row['name'] }}
                        </span>
                        <span class="small num">{{ money($row['revenue']) }}</span>
                    </div>
                    <div class="meter">
                        <div class="meter__fill" style="width:{{ ($row['revenue'] / $maxRevenue) * 100 }}%"></div>
                    </div>
                    <div class="tiny subtle mt-4">
                        {{ $row['transactions'] }} transaksi · rata-rata {{ money($row['average_basket']) }}
                        @allow('report.profit') · laba {{ money($row['profit']) }} @endallow
                    </div>
                </div>
            @endforeach
        </div>
    </div>
@endif

<div class="grid grid-2-1 mb-20">
    {{-- Revenue trend --}}
    <div class="card">
        <div class="card__head">
            <div>
                <div class="card__title">Tren Penjualan</div>
                <div class="card__sub">Omzet harian pada periode terpilih</div>
            </div>
            <div class="row g-16">
                <div class="t-right">
                    <div class="tiny subtle upper">Total</div>
                    <div class="semi">{{ money($c['current']['revenue']) }}</div>
                </div>
                <div class="divider--v"></div>
                <div class="t-right">
                    <div class="tiny subtle upper">Diskon</div>
                    <div class="semi">{{ money($c['current']['discount']) }}</div>
                </div>
            </div>
        </div>
        <div class="card__body">
            <x-line-chart :series="$series" value-key="revenue" id="revenue" :height="216"/>
        </div>
    </div>

    {{-- Payment mix --}}
    <div class="card">
        <div class="card__head">
            <div class="card__title">Metode Pembayaran</div>
        </div>
        <div class="card__body">
            @forelse ($payments as $payment)
                <div class="mb-16">
                    <div class="between mb-4">
                        <span class="small semi">{{ $payment['label'] }}</span>
                        <span class="small num">{{ money($payment['amount']) }}</span>
                    </div>
                    <div class="meter"><div class="meter__fill" style="width:{{ min(100, $payment['share']) }}%"></div></div>
                    <div class="tiny subtle mt-4">{{ $payment['count'] }} transaksi · {{ percent_label($payment['share']) }}</div>
                </div>
            @empty
                <div class="empty">
                    <div class="empty__icon"><x-icon name="wallet" size="22"/></div>
                    <div class="empty__title">Belum ada pembayaran</div>
                    <div class="empty__text">Data akan muncul setelah ada transaksi pada periode ini.</div>
                </div>
            @endforelse
        </div>
    </div>
</div>

<div class="grid grid-2 mb-20">
    {{-- Best sellers --}}
    <div class="card">
        <div class="card__head">
            <div class="card__title">Produk Terlaris</div>
            <a href="{{ route('admin.reports.show', ['report' => 'products', 'from' => $from, 'to' => $to]) }}"
               class="btn btn--ghost btn--sm">Lihat semua <x-icon name="chevron-right" size="14"/></a>
        </div>
        <div class="table-wrap">
            <table class="table table--compact">
                <thead>
                    <tr>
                        <th>Produk</th>
                        <th class="t-right">Qty</th>
                        <th class="t-right">Omzet</th>
                        @allow('report.profit')<th class="t-right">Margin</th>@endallow
                    </tr>
                </thead>
                <tbody>
                    @forelse ($topProducts as $product)
                        <tr>
                            <td>
                                <div class="semi">{{ $product['name'] }}</div>
                                <div class="tiny subtle">{{ $product['category'] }}</div>
                            </td>
                            <td class="t-right num">{{ qty_label($product['qty']) }}</td>
                            <td class="t-right num semi">{{ money($product['revenue']) }}</td>
                            @allow('report.profit')
                                <td class="t-right num">
                                    <span class="badge badge--{{ $product['margin_percent'] >= 30 ? 'ok' : 'warn' }}">
                                        {{ percent_label($product['margin_percent']) }}
                                    </span>
                                </td>
                            @endallow
                        </tr>
                    @empty
                        <tr><td colspan="4"><div class="empty"><div class="empty__title">Belum ada penjualan</div></div></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    {{-- Low stock --}}
    <div class="card">
        <div class="card__head">
            <div>
                <div class="card__title">Stok Menipis</div>
                <div class="card__sub">Produk yang perlu segera diisi ulang</div>
            </div>
            @allow('stock.view')
                <a href="{{ route('admin.stock.index') }}" class="btn btn--ghost btn--sm">
                    Kelola stok <x-icon name="chevron-right" size="14"/>
                </a>
            @endallow
        </div>
        <div class="table-wrap">
            <table class="table table--compact">
                <thead>
                    <tr><th>Produk</th><th class="t-right">Sisa</th><th class="t-right">Minimum</th><th></th></tr>
                </thead>
                <tbody>
                    @forelse ($lowStock as $product)
                        <tr>
                            <td>
                                <div class="semi">{{ $product->name }}</div>
                                <div class="tiny subtle mono">{{ $product->sku }}</div>
                            </td>
                            <td class="t-right num semi {{ $product->isOutOfStock() ? 'bad' : 'warn' }}">
                                {{ qty_label($product->stock) }} {{ $product->unit }}
                            </td>
                            <td class="t-right num subtle">{{ qty_label($product->min_stock) }}</td>
                            <td class="t-right">
                                <span class="badge badge--{{ $product->isOutOfStock() ? 'bad' : 'warn' }}">
                                    {{ $product->isOutOfStock() ? 'Habis' : 'Menipis' }}
                                </span>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="4">
                            <div class="empty">
                                <div class="empty__icon" style="background:var(--ok-50);color:var(--ok-600)">
                                    <x-icon name="check-circle" size="22"/>
                                </div>
                                <div class="empty__title">Semua stok aman</div>
                                <div class="empty__text">Tidak ada produk yang berada di bawah batas minimum.</div>
                            </div>
                        </td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>

<div class="grid grid-2-1">
    {{-- Recent transactions --}}
    <div class="card">
        <div class="card__head">
            <div class="card__title">Transaksi Terakhir</div>
            @allow('sale.view')
                <a href="{{ route('admin.sales.index') }}" class="btn btn--ghost btn--sm">
                    Semua transaksi <x-icon name="chevron-right" size="14"/>
                </a>
            @endallow
        </div>
        <div class="table-wrap">
            <table class="table table--compact">
                <thead>
                    <tr><th>Invoice</th><th>Kasir</th><th>Waktu</th><th class="t-right">Total</th><th></th></tr>
                </thead>
                <tbody>
                    @forelse ($recentSales as $sale)
                        <tr>
                            <td>
                                @allow('sale.view')
                                    <a href="{{ route('admin.sales.show', $sale) }}" class="mono semi">{{ $sale->invoice_number }}</a>
                                @else
                                    <span class="mono semi">{{ $sale->invoice_number }}</span>
                                @endallow
                                <div class="tiny subtle">{{ $sale->customer?->name ?? 'Pelanggan Umum' }}</div>
                            </td>
                            <td class="small">{{ $sale->user?->name }}</td>
                            <td class="small muted nowrap">{{ $sale->created_at->format('d/m H:i') }}</td>
                            <td class="t-right num semi">{{ money($sale->total) }}</td>
                            <td class="t-right">
                                <span class="badge badge--{{ $sale->status === 'completed' ? 'ok' : 'bad' }}">
                                    {{ $sale->status === 'completed' ? 'Selesai' : 'Batal' }}
                                </span>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="5"><div class="empty"><div class="empty__title">Belum ada transaksi</div></div></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    {{-- Open drawers --}}
    <div class="card">
        <div class="card__head">
            <div class="card__title">Shift Berjalan</div>
        </div>
        <div class="card__body">
            @forelse ($openShifts as $shift)
                <div class="between" style="padding:11px 0;border-bottom:1px solid var(--border)">
                    <div class="row g-10">
                        <span class="avatar avatar--sm">{{ $shift->user?->initials() }}</span>
                        <div>
                            <div class="semi small">{{ $shift->user?->name }}</div>
                            <div class="tiny subtle">
                                Buka {{ $shift->opened_at->format('H:i') }} · {{ $shift->durationLabel() }}
                            </div>
                        </div>
                    </div>
                    <div class="t-right">
                        <div class="semi small num">{{ money($shift->total_sales) }}</div>
                        <div class="tiny subtle">{{ $shift->total_transactions }} trx</div>
                    </div>
                </div>
            @empty
                <div class="empty">
                    <div class="empty__icon"><x-icon name="clock" size="22"/></div>
                    <div class="empty__title">Tidak ada shift terbuka</div>
                    <div class="empty__text">Kasir belum membuka laci hari ini.</div>
                </div>
            @endforelse

            <div class="divider"></div>

            <div class="row between small">
                <span class="muted">Produk aktif</span>
                <span class="semi">{{ number_format($productCount, 0, ',', '.') }}</span>
            </div>
            <div class="row between small mt-8">
                <span class="muted">Transaksi dibatalkan</span>
                <span class="semi {{ $c['current']['voided_count'] > 0 ? 'bad' : '' }}">
                    {{ $c['current']['voided_count'] }} · {{ money($c['current']['voided_value']) }}
                </span>
            </div>
        </div>
    </div>
</div>

@endsection
