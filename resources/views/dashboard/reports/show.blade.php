@extends('layouts.app')

@section('title', $title)
@section('subtitle', $subtitle)

@section('content')

<div class="page-head">
    <div class="row g-12">
        <a href="{{ route('admin.reports.index', ['from' => $from, 'to' => $to]) }}" class="btn btn--ghost btn--icon">
            <x-icon name="arrow-left" size="18"/>
        </a>
        <div>
            <h1>{{ $title }}</h1>
            <p class="muted mt-4">
                <span class="badge badge--{{ $outlet ? 'neutral' : 'violet' }}">
                    <x-icon name="{{ $outlet ? 'store' : 'layers' }}" size="11"/>
                    {{ $outlet?->name ?? 'Semua Outlet' }}
                </span>
                · {{ \Carbon\Carbon::parse($from)->translatedFormat('d M Y') }}
                – {{ \Carbon\Carbon::parse($to)->translatedFormat('d M Y') }}
                · {{ count($rows) }} baris
            </p>
        </div>
    </div>

    <div class="row g-8 wrap">
        <form method="GET" class="row g-8 wrap" data-auto-submit>
            <input type="date" name="from" value="{{ $from }}" class="input" style="width:auto">
            <input type="date" name="to" value="{{ $to }}" class="input" style="width:auto">

            @if ($report === 'sales')
                <select name="cashier" class="select" style="width:auto;min-width:150px">
                    <option value="">Semua kasir</option>
                    @foreach ($cashiers as $cashier)
                        <option value="{{ $cashier->id }}" @selected(($filters['cashier'] ?? '') == $cashier->id)>
                            {{ $cashier->name }}
                        </option>
                    @endforeach
                </select>
            @endif

            <button type="submit" class="btn btn--outline"><x-icon name="filter" size="15"/></button>
        </form>

        @allow('report.export')
            <a href="{{ route('admin.reports.export', array_merge(['report' => $report, 'format' => 'pdf'], request()->query(), ['from' => $from, 'to' => $to])) }}"
               class="btn btn--primary">
                <x-icon name="download" size="16"/> Export PDF
            </a>
            <a href="{{ route('admin.reports.export', array_merge(['report' => $report, 'format' => 'csv'], request()->query(), ['from' => $from, 'to' => $to])) }}"
               class="btn btn--outline">
                <x-icon name="file-text" size="16"/> CSV
            </a>
        @endallow
    </div>
</div>

{{-- Headline figures, shown on every report for context --}}
<div class="grid grid-5 mb-20">
    <div class="stat stat--accent">
        <div class="stat__label">Omzet</div>
        <div class="stat__value" style="font-size:21px">{{ money($summary['revenue']) }}</div>
    </div>
    <div class="stat">
        <div class="stat__label">Transaksi</div>
        <div class="stat__value" style="font-size:21px">{{ number_format($summary['transactions'], 0, ',', '.') }}</div>
    </div>
    <div class="stat">
        <div class="stat__label">Rata-rata</div>
        <div class="stat__value" style="font-size:21px">{{ money($summary['average_basket']) }}</div>
    </div>
    @allow('report.profit')
        <div class="stat">
            <div class="stat__label">Laba</div>
            <div class="stat__value" style="font-size:21px">{{ money($summary['profit']) }}</div>
        </div>
        <div class="stat">
            <div class="stat__label">Margin</div>
            <div class="stat__value" style="font-size:21px">{{ percent_label($summary['margin_percent']) }}</div>
        </div>
    @endallow
</div>

{{-- Report-specific visuals --}}
@if (in_array($report, ['summary', 'profit']) && isset($series))
    <div class="card mb-20">
        <div class="card__head">
            <div class="card__title">{{ $report === 'profit' ? 'Tren Laba Harian' : 'Tren Omzet Harian' }}</div>
        </div>
        <div class="card__body">
            <x-line-chart :series="$series" :value-key="$report === 'profit' ? 'profit' : 'revenue'" id="report" :height="230"/>
        </div>
    </div>
@endif

@if ($report === 'categories' && isset($data))
    <div class="card mb-20">
        <div class="card__head"><div class="card__title">Kontribusi Kategori</div></div>
        <div class="card__body">
            @php $maxRevenue = collect($data)->max('revenue') ?: 1; @endphp
            @foreach ($data as $row)
                <div class="mb-16">
                    <div class="between mb-4">
                        <span class="small semi">
                            <span class="dot" style="background:{{ $row['color'] }};display:inline-block;margin-right:6px"></span>
                            {{ $row['category'] }}
                        </span>
                        <span class="small num">{{ money($row['revenue']) }}</span>
                    </div>
                    <div class="meter">
                        <div class="meter__fill" style="width:{{ ($row['revenue'] / $maxRevenue) * 100 }}%;background:{{ $row['color'] }}"></div>
                    </div>
                </div>
            @endforeach
        </div>
    </div>
@endif

@if ($report === 'payments' && isset($data))
    <div class="grid grid-4 mb-20">
        @foreach ($data as $row)
            <div class="stat">
                <div class="stat__label">{{ $row['label'] }}</div>
                <div class="stat__value" style="font-size:20px">{{ money($row['amount']) }}</div>
                <div class="stat__meta">{{ $row['count'] }} transaksi · {{ percent_label($row['share']) }}</div>
            </div>
        @endforeach
    </div>
@endif

@if ($report === 'inventory' && isset($inventory))
    <div class="grid grid-4 mb-20">
        <div class="stat">
            <div class="stat__label">Nilai Modal</div>
            <div class="stat__value" style="font-size:21px">{{ money($inventory['total_value_cost']) }}</div>
        </div>
        <div class="stat">
            <div class="stat__label">Nilai Jual</div>
            <div class="stat__value" style="font-size:21px">{{ money($inventory['total_value_retail']) }}</div>
        </div>
        <div class="stat">
            <div class="stat__label">Stok Menipis</div>
            <div class="stat__value warn" style="font-size:21px">{{ $inventory['low_stock_count'] }}</div>
        </div>
        <div class="stat">
            <div class="stat__label">Stok Habis</div>
            <div class="stat__value bad" style="font-size:21px">{{ $inventory['out_of_stock_count'] }}</div>
        </div>
    </div>
@endif

{{-- The tabular payload, identical to what the PDF and CSV receive --}}
<div class="card">
    <div class="card__head">
        <div>
            <div class="card__title">Rincian</div>
            <div class="card__sub">Dibuat {{ $generatedAt->translatedFormat('d F Y H:i') }} oleh {{ $generatedBy }}</div>
        </div>
    </div>

    <div class="table-wrap">
        <table class="table">
            <thead>
                <tr>
                    @foreach ($columns as $index => $column)
                        <th class="{{ $index > 0 ? 't-right' : '' }}">{{ $column }}</th>
                    @endforeach
                </tr>
            </thead>
            <tbody>
                @forelse ($rows as $row)
                    <tr>
                        @foreach ($row as $index => $cell)
                            <td class="{{ $index > 0 ? 't-right num' : 'semi' }}">{{ $cell }}</td>
                        @endforeach
                    </tr>
                @empty
                    <tr>
                        <td colspan="{{ max(1, count($columns)) }}">
                            <div class="empty">
                                <div class="empty__icon"><x-icon name="inbox" size="24"/></div>
                                <div class="empty__title">Tidak ada data pada periode ini</div>
                                <div class="empty__text">Coba perlebar rentang tanggal atau ubah filter.</div>
                            </div>
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

@endsection
