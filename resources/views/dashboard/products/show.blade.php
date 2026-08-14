@extends('layouts.app')

@section('title', $product->name)
@section('subtitle', 'Detail produk, barcode, QR, dan riwayat stok')

@section('content')

<div class="page-head">
    <div class="row g-12">
        <a href="{{ route('admin.products.index') }}" class="btn btn--ghost btn--icon">
            <x-icon name="arrow-left" size="18"/>
        </a>
        <div>
            <h1>{{ $product->name }}</h1>
            <div class="row g-8 mt-4 wrap">
                <span class="code-chip">{{ $product->sku }}</span>
                @if ($product->category)
                    <span class="badge badge--neutral">
                        <span class="dot" style="background:{{ $product->category->color }}"></span>
                        {{ $product->category->name }}
                    </span>
                @endif
                <span class="badge badge--{{ $product->is_active ? 'ok' : 'neutral' }}">
                    {{ $product->is_active ? 'Aktif' : 'Nonaktif' }}
                </span>
            </div>
        </div>
    </div>

    <div class="row g-8 wrap">
        <a href="{{ route('admin.products.labels', ['ids' => [$product->id], 'copies' => 12]) }}"
           target="_blank" class="btn btn--outline">
            <x-icon name="printer" size="16"/> Cetak Label
        </a>
        @allow('product.update')
            <form method="POST" action="{{ route('admin.products.regenerate', $product) }}">
                @csrf
                <button type="submit" class="btn btn--outline"
                        data-confirm="Buat ulang ID produk, barcode, dan QR? Label lama akan berbeda.">
                    <x-icon name="refresh" size="16"/> Buat Ulang ID
                </button>
            </form>
            <a href="{{ route('admin.products.edit', $product) }}" class="btn btn--primary">
                <x-icon name="edit" size="16"/> Ubah
            </a>
        @endallow
    </div>
</div>

<div class="grid grid-4 mb-20">
    <div class="stat">
        <div class="stat__label">Harga Jual</div>
        <div class="stat__value">{{ money($product->price) }}</div>
        <div class="stat__meta">Modal {{ money($product->cost_price) }}</div>
    </div>
    @allow('report.profit')
        <div class="stat">
            <div class="stat__label">Margin</div>
            <div class="stat__value">{{ percent_label($product->marginPercent()) }}</div>
            <div class="stat__meta">Laba {{ money((float) $product->price - (float) $product->cost_price) }} / {{ $product->unit }}</div>
        </div>
    @endallow
    <div class="stat">
        <div class="stat__label">Stok Saat Ini</div>
        <div class="stat__value {{ $product->isOutOfStock() ? 'bad' : ($product->isLowStock() ? 'warn' : '') }}">
            {{ $product->track_stock ? qty_label($product->stock) . ' ' . $product->unit : '∞' }}
        </div>
        <div class="stat__meta">Minimum {{ qty_label($product->min_stock) }} {{ $product->unit }}</div>
    </div>
    <div class="stat">
        <div class="stat__label">Terjual</div>
        <div class="stat__value">{{ number_format($product->sold_count, 0, ',', '.') }}</div>
        <div class="stat__meta">Akumulasi sejak produk dibuat</div>
    </div>
</div>

<div class="grid grid-1-2">
    {{-- Scannable marks --}}
    <div class="card">
        <div class="card__head">
            <div>
                <div class="card__title">Barcode &amp; QR</div>
                <div class="card__sub">Dibuat otomatis dari ID produk</div>
            </div>
        </div>
        <div class="card__body t-center">
            <div class="tiny subtle upper mb-8">Barcode {{ $product->barcode_type }}</div>

            @if ($barcodeSvg)
                <div style="background:#fff;padding:14px;border-radius:var(--r-md);border:1px solid var(--border)">
                    {!! $barcodeSvg !!}
                    <div class="mono small mt-8" style="color:#111;letter-spacing:.08em">{{ $product->barcode_value }}</div>
                </div>
            @else
                <div class="alert alert--warn">
                    <x-icon name="alert" size="16" class="alert__icon"/>
                    <div>Nilai barcode tidak sesuai format {{ $product->barcode_type }}. Perbarui melalui tombol Buat Ulang ID.</div>
                </div>
            @endif

            <div class="divider"></div>

            <div class="tiny subtle upper mb-8">QR Code</div>
            <div style="background:#fff;padding:14px;border-radius:var(--r-md);border:1px solid var(--border);display:inline-block">
                <div style="width:150px">{!! $qrSvg !!}</div>
            </div>
            <div class="tiny subtle mt-8">Berisi <span class="mono">{{ $product->qr_value }}</span></div>
        </div>
    </div>

    {{-- Stock ledger --}}
    <div class="card">
        <div class="card__head">
            <div>
                <div class="card__title">Riwayat Pergerakan Stok</div>
                <div class="card__sub">20 pergerakan terakhir</div>
            </div>
            @allow('stock.view')
                <a href="{{ route('admin.stock.index', ['product' => $product->id]) }}" class="btn btn--ghost btn--sm">
                    Semua riwayat <x-icon name="chevron-right" size="14"/>
                </a>
            @endallow
        </div>
        <div class="table-wrap">
            <table class="table table--compact">
                <thead>
                    <tr>
                        <th>Waktu</th><th>Jenis</th><th class="t-right">Perubahan</th>
                        <th class="t-right">Saldo</th><th>Oleh</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($movements as $movement)
                        <tr>
                            <td class="small muted nowrap">{{ $movement->created_at->format('d/m/y H:i') }}</td>
                            <td><span class="badge badge--neutral">{{ $movement->typeLabel() }}</span></td>
                            <td class="t-right num semi {{ (float) $movement->qty >= 0 ? 'ok' : 'bad' }}">
                                {{ (float) $movement->qty >= 0 ? '+' : '' }}{{ qty_label($movement->qty) }}
                            </td>
                            <td class="t-right num">{{ qty_label($movement->stock_after) }}</td>
                            <td class="small muted">{{ $movement->user?->name ?? 'Sistem' }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="5"><div class="empty"><div class="empty__title">Belum ada pergerakan stok</div></div></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>

@if ($product->description)
    <div class="card mt-20">
        <div class="card__head"><div class="card__title">Deskripsi</div></div>
        <div class="card__body"><p class="muted">{{ $product->description }}</p></div>
    </div>
@endif

@endsection
