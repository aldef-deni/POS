@extends('layouts.app')

@section('title', 'Produk')
@section('subtitle', 'Katalog produk beserta ID, barcode, dan QR otomatis')

@section('content')

<div class="page-head">
    <div>
        <h1>Produk</h1>
        <p class="muted mt-4">{{ $products->total() }} produk terdaftar. Setiap produk baru langsung menerima ID, barcode, dan QR.</p>
    </div>

    <div class="row g-8 wrap">
        <a href="{{ route('admin.products.labels') }}" target="_blank" class="btn btn--outline">
            <x-icon name="printer" size="16"/> Cetak Label
        </a>
        @allow('product.create')
            <a href="{{ route('admin.products.create') }}" class="btn btn--primary">
                <x-icon name="plus" size="16"/> Produk Baru
            </a>
        @endallow
    </div>
</div>

<div class="card mb-20">
    <div class="card__body card__body--tight">
        <form method="GET" class="row g-8 wrap" data-search-form data-auto-submit>
            <div class="search grow" style="min-width:220px">
                <x-icon name="search" size="16" class="search__icon"/>
                <input type="text" name="q" value="{{ $filters['q'] ?? '' }}" class="input"
                       placeholder="Cari nama, SKU, atau barcode…">
            </div>

            <select name="category" class="select" style="width:auto;min-width:150px">
                <option value="">Semua kategori</option>
                @foreach ($categories as $category)
                    <option value="{{ $category->id }}" @selected(($filters['category'] ?? '') == $category->id)>
                        {{ $category->name }}
                    </option>
                @endforeach
            </select>

            <select name="status" class="select" style="width:auto;min-width:140px">
                <option value="">Semua status</option>
                <option value="active" @selected(($filters['status'] ?? '') === 'active')>Aktif</option>
                <option value="inactive" @selected(($filters['status'] ?? '') === 'inactive')>Nonaktif</option>
                <option value="low" @selected(($filters['status'] ?? '') === 'low')>Stok menipis</option>
            </select>

            <button type="submit" class="btn btn--outline"><x-icon name="filter" size="15"/> Filter</button>

            @if (array_filter($filters))
                <a href="{{ route('admin.products.index') }}" class="btn btn--ghost">Reset</a>
            @endif
        </form>
    </div>
</div>

<div class="card">
    <div class="table-wrap">
        <table class="table">
            <thead>
                <tr>
                    <th>Produk</th>
                    <th>ID Produk</th>
                    <th>Kategori</th>
                    <th class="t-right">Modal</th>
                    <th class="t-right">Harga Jual</th>
                    @allow('report.profit')<th class="t-right">Margin</th>@endallow
                    <th class="t-right">Stok</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                @forelse ($products as $product)
                    <tr>
                        <td>
                            <div class="row g-10">
                                <span class="avatar avatar--sm" style="background:{{ $product->category?->color ?? '#64748B' }}">
                                    {{ mb_substr($product->name, 0, 1) }}
                                </span>
                                <div style="min-width:0">
                                    <a href="{{ route('admin.products.show', $product) }}" class="semi">{{ $product->name }}</a>
                                    <div class="tiny subtle">
                                        {{ $product->unit }}
                                        @if ($product->is_favorite) · <span class="ok">favorit kasir</span> @endif
                                        @unless ($product->is_active) · <span class="bad">nonaktif</span> @endunless
                                    </div>
                                </div>
                            </div>
                        </td>
                        <td>
                            <span class="code-chip">{{ $product->sku }}</span>
                            <div class="tiny subtle mt-4">{{ $product->barcode_type }} · {{ $product->barcode_value }}</div>
                        </td>
                        <td>
                            @if ($product->category)
                                <span class="badge badge--neutral">
                                    <span class="dot" style="background:{{ $product->category->color }}"></span>
                                    {{ $product->category->name }}
                                </span>
                            @else
                                <span class="subtle">—</span>
                            @endif
                        </td>
                        <td class="t-right num muted">{{ money($product->cost_price) }}</td>
                        <td class="t-right num semi">{{ money($product->price) }}</td>
                        @allow('report.profit')
                            <td class="t-right num">
                                <span class="badge badge--{{ $product->marginPercent() >= 30 ? 'ok' : ($product->marginPercent() > 0 ? 'warn' : 'bad') }}">
                                    {{ percent_label($product->marginPercent()) }}
                                </span>
                            </td>
                        @endallow
                        <td class="t-right num">
                            @if ($product->track_stock)
                                <span class="semi {{ $product->isOutOfStock() ? 'bad' : ($product->isLowStock() ? 'warn' : '') }}">
                                    {{ qty_label($product->stock) }}
                                </span>
                                <div class="tiny subtle">min {{ qty_label($product->min_stock) }}</div>
                            @else
                                <span class="subtle">tidak dilacak</span>
                            @endif
                        </td>
                        <td class="t-right">
                            <div class="dropdown">
                                <button type="button" class="btn btn--ghost btn--icon" data-dropdown>⋯</button>
                                <div class="dropdown__menu">
                                    <a href="{{ route('admin.products.show', $product) }}" class="dropdown__item">
                                        <x-icon name="qr" size="15"/> Detail &amp; Barcode
                                    </a>
                                    @allow('product.update')
                                        <a href="{{ route('admin.products.edit', $product) }}" class="dropdown__item">
                                            <x-icon name="edit" size="15"/> Ubah
                                        </a>
                                    @endallow
                                    <a href="{{ route('admin.products.labels', ['ids' => [$product->id], 'copies' => 12]) }}"
                                       target="_blank" class="dropdown__item">
                                        <x-icon name="printer" size="15"/> Cetak label
                                    </a>
                                    @allow('product.delete')
                                        <div class="dropdown__sep"></div>
                                        <form method="POST" action="{{ route('admin.products.destroy', $product) }}">
                                            @csrf @method('DELETE')
                                            <button type="submit" class="dropdown__item dropdown__item--danger"
                                                    data-confirm="Hapus produk {{ $product->name }}?">
                                                <x-icon name="trash" size="15"/> Hapus
                                            </button>
                                        </form>
                                    @endallow
                                </div>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="8">
                            <div class="empty">
                                <div class="empty__icon"><x-icon name="package" size="24"/></div>
                                <div class="empty__title">Belum ada produk</div>
                                <div class="empty__text">Tambahkan produk pertama Anda — ID, barcode, dan QR akan dibuat otomatis.</div>
                                @allow('product.create')
                                    <a href="{{ route('admin.products.create') }}" class="btn btn--primary mt-16">
                                        <x-icon name="plus" size="16"/> Produk Baru
                                    </a>
                                @endallow
                            </div>
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{ $products->links() }}
</div>

@endsection
