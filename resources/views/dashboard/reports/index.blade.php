@extends('layouts.app')

@section('title', 'Laporan')
@section('subtitle', 'Sepuluh jenis laporan dengan rentang tanggal bebas')

@section('content')

@php
    $icons = [
        'summary' => 'chart', 'sales' => 'receipt', 'products' => 'package',
        'categories' => 'tags', 'cashiers' => 'users', 'payments' => 'wallet',
        'profit' => 'trending-up', 'inventory' => 'boxes', 'shifts' => 'clock',
        'voids' => 'alert',
    ];

    $presets = [
        'Hari ini' => [today()->toDateString(), today()->toDateString()],
        '7 hari' => [today()->subDays(6)->toDateString(), today()->toDateString()],
        '30 hari' => [today()->subDays(29)->toDateString(), today()->toDateString()],
        'Bulan ini' => [today()->startOfMonth()->toDateString(), today()->toDateString()],
        'Bulan lalu' => [today()->subMonthNoOverflow()->startOfMonth()->toDateString(), today()->subMonthNoOverflow()->endOfMonth()->toDateString()],
    ];
@endphp

<div class="page-head">
    <div>
        <h1>Pusat Laporan</h1>
        <p class="muted mt-4">
            Periode {{ \Carbon\Carbon::parse($from)->translatedFormat('d M Y') }}
            – {{ \Carbon\Carbon::parse($to)->translatedFormat('d M Y') }}
        </p>
    </div>

    <form method="GET" class="row g-8 wrap" data-auto-submit>
        <input type="date" name="from" value="{{ $from }}" class="input" style="width:auto">
        <span class="muted small">s/d</span>
        <input type="date" name="to" value="{{ $to }}" class="input" style="width:auto">
        <button type="submit" class="btn btn--outline"><x-icon name="filter" size="15"/> Terapkan</button>
    </form>
</div>

<div class="row g-6 wrap mb-20">
    @foreach ($presets as $label => [$pFrom, $pTo])
        <a href="{{ route('admin.reports.index', ['from' => $pFrom, 'to' => $pTo]) }}"
           class="btn btn--sm {{ $from === $pFrom && $to === $pTo ? 'btn--soft' : 'btn--outline' }}">
            {{ $label }}
        </a>
    @endforeach
</div>

<div class="grid grid-4 mb-24">
    <div class="stat stat--accent">
        <div class="stat__label">Omzet</div>
        <div class="stat__value">{{ money($summary['revenue']) }}</div>
        <div class="stat__meta">{{ $summary['transactions'] }} transaksi</div>
    </div>
    <div class="stat">
        <div class="stat__label">Rata-rata Belanja</div>
        <div class="stat__value">{{ money($summary['average_basket']) }}</div>
        <div class="stat__meta">{{ qty_label($summary['qty']) }} item terjual</div>
    </div>
    @allow('report.profit')
        <div class="stat">
            <div class="stat__label">Laba Kotor</div>
            <div class="stat__value">{{ money($summary['profit']) }}</div>
            <div class="stat__meta">Margin {{ percent_label($summary['margin_percent']) }}</div>
        </div>
    @endallow
    <div class="stat">
        <div class="stat__label">Dibatalkan</div>
        <div class="stat__value {{ $summary['voided_count'] > 0 ? 'bad' : '' }}">{{ $summary['voided_count'] }}</div>
        <div class="stat__meta">Senilai {{ money($summary['voided_value']) }}</div>
    </div>
</div>

<div class="grid grid-3">
    @foreach ($reports as $key => [$title, $subtitle])
        <div class="card card--pad">
            <div class="row g-12 mb-12">
                <div class="stat__icon" style="position:static">
                    <x-icon :name="$icons[$key] ?? 'file-text'" size="18"/>
                </div>
                <div class="grow">
                    <div class="semi">{{ $title }}</div>
                </div>
            </div>

            <p class="small muted" style="min-height:38px">{{ $subtitle }}</p>

            <div class="divider"></div>

            <div class="row g-6">
                <a href="{{ route('admin.reports.show', ['report' => $key, 'from' => $from, 'to' => $to]) }}"
                   class="btn btn--soft btn--sm grow">
                    Buka Laporan
                </a>
                @allow('report.export')
                    <a href="{{ route('admin.reports.export', ['report' => $key, 'format' => 'pdf', 'from' => $from, 'to' => $to]) }}"
                       class="btn btn--outline btn--sm" title="Unduh PDF">
                        <x-icon name="download" size="14"/> PDF
                    </a>
                @endallow
            </div>
        </div>
    @endforeach
</div>

@endsection
