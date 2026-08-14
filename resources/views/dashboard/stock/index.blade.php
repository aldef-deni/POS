@extends('layouts.app')

@section('title', 'Stok & Inventori')
@section('subtitle', 'Buku besar pergerakan stok dan peringatan stok menipis')

@section('content')

<div class="page-head">
    <div>
        <h1>Stok &amp; Inventori</h1>
        <p class="muted mt-4">
            @if ($activeOutlet)
                Menampilkan stok <b>{{ $activeOutlet->name }}</b>. Setiap perubahan tercatat lengkap
                dengan saldo sebelum dan sesudah.
            @else
                Menampilkan <b>seluruh outlet</b>. Pilih satu outlet di bagian atas untuk menyesuaikan stok.
            @endif
        </p>
    </div>
    <div class="row g-8">
        <a href="{{ route('admin.stock.opname') }}" class="btn btn--outline">
            <x-icon name="layers" size="16"/> Stok Opname
        </a>
        @allow('stock.adjust')
            {{-- Adjusting needs a destination branch, so the control is
                 disabled rather than failing after the fact. --}}
            <button type="button" class="btn btn--primary {{ $activeOutlet ? '' : 'is-disabled' }}"
                    @if ($activeOutlet) data-modal-open="adjust-modal" @else disabled
                    title="Pilih satu outlet terlebih dahulu" @endif>
                <x-icon name="plus" size="16"/> Penyesuaian Stok
            </button>
        @endallow
    </div>
</div>

@if ($lowStock->isNotEmpty())
    <div class="card mb-20" style="border-color:var(--warn-200)">
        <div class="card__head" style="background:var(--warn-50);border-radius:var(--r-lg) var(--r-lg) 0 0">
            <div class="row g-10">
                <x-icon name="alert" size="18" style="color:var(--warn-600)"/>
                <div>
                    <div class="card__title" style="color:var(--warn-700)">
                        {{ $lowStock->count() }} produk perlu diisi ulang
                    </div>
                    <div class="card__sub">Stok berada pada atau di bawah batas minimum</div>
                </div>
            </div>
        </div>
        <div class="card__body card__body--tight">
            <div class="row g-8 wrap">
                @foreach ($lowStock as $product)
                    @php $onHand = $product->stockAt($activeOutlet?->id); @endphp
                    <a href="{{ route('admin.products.show', $product) }}"
                       class="badge badge--{{ $onHand <= 0 ? 'bad' : 'warn' }}"
                       style="padding:6px 11px">
                        {{ $product->name }} · {{ qty_label($onHand) }} {{ $product->unit }}
                    </a>
                @endforeach
            </div>
        </div>
    </div>
@endif

<div class="card mb-20">
    <div class="card__body card__body--tight">
        <form method="GET" class="row g-8 wrap" data-auto-submit>
            <select name="product" class="select" style="width:auto;min-width:220px">
                <option value="">Semua produk</option>
                @foreach ($products as $product)
                    <option value="{{ $product->id }}" @selected(($filters['product'] ?? '') == $product->id)>
                        {{ $product->name }}
                    </option>
                @endforeach
            </select>

            <select name="type" class="select" style="width:auto;min-width:170px">
                <option value="">Semua jenis</option>
                @foreach ($types as $value => $label)
                    <option value="{{ $value }}" @selected(($filters['type'] ?? '') === $value)>{{ $label }}</option>
                @endforeach
            </select>

            <button type="submit" class="btn btn--outline"><x-icon name="filter" size="15"/> Filter</button>
            @if (array_filter($filters))
                <a href="{{ route('admin.stock.index') }}" class="btn btn--ghost">Reset</a>
            @endif
        </form>
    </div>
</div>

<div class="card">
    <div class="table-wrap">
        <table class="table">
            <thead>
                <tr>
                    <th>Waktu</th><th>Outlet</th><th>Produk</th><th>Jenis</th>
                    <th class="t-right">Perubahan</th><th class="t-right">Sebelum</th>
                    <th class="t-right">Sesudah</th><th>Catatan</th><th>Oleh</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($movements as $movement)
                    <tr>
                        <td class="small muted nowrap">{{ $movement->created_at->format('d/m/y H:i') }}</td>
                        <td><span class="code-chip">{{ $movement->outlet?->code ?? '—' }}</span></td>
                        <td>
                            <a href="{{ route('admin.products.show', $movement->product_id) }}" class="semi small">
                                {{ $movement->product?->name ?? '—' }}
                            </a>
                            <div class="tiny subtle mono">{{ $movement->product?->sku }}</div>
                        </td>
                        <td>
                            <span class="badge badge--{{ in_array($movement->type, ['in', 'void_return']) ? 'ok' : ($movement->type === 'sale' ? 'info' : 'neutral') }}">
                                {{ $movement->typeLabel() }}
                            </span>
                        </td>
                        <td class="t-right num semi {{ (float) $movement->qty >= 0 ? 'ok' : 'bad' }}">
                            {{ (float) $movement->qty >= 0 ? '+' : '' }}{{ qty_label($movement->qty) }}
                        </td>
                        <td class="t-right num muted">{{ qty_label($movement->stock_before) }}</td>
                        <td class="t-right num semi">{{ qty_label($movement->stock_after) }}</td>
                        <td class="small muted">{{ $movement->note ?? '—' }}</td>
                        <td class="small muted">{{ $movement->user?->name ?? 'Sistem' }}</td>
                    </tr>
                @empty
                    <tr><td colspan="9">
                        <div class="empty">
                            <div class="empty__icon"><x-icon name="boxes" size="24"/></div>
                            <div class="empty__title">Belum ada pergerakan stok</div>
                        </div>
                    </td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{ $movements->links() }}
</div>

@allow('stock.adjust')
<div class="modal" id="adjust-modal">
    <div class="modal__backdrop"></div>
    <div class="modal__panel modal__panel--narrow">
        <form method="POST" action="{{ route('admin.stock.adjust') }}">
            @csrf
            <div class="modal__head">
                <div class="modal__title">Penyesuaian Stok</div>
                <div class="modal__sub">Catat barang masuk atau keluar di luar penjualan</div>
            </div>

            <div class="modal__body">
                <div class="field">
                    <label class="field__label">Produk <span class="field__req">*</span></label>
                    <select name="product_id" class="select" required data-autofocus>
                        <option value="">— Pilih produk —</option>
                        @foreach ($products as $product)
                            <option value="{{ $product->id }}">
                                {{ $product->name }} (stok {{ qty_label($product->stock) }} {{ $product->unit }})
                            </option>
                        @endforeach
                    </select>
                </div>

                <div class="grid grid-2">
                    <div class="field">
                        <label class="field__label">Arah <span class="field__req">*</span></label>
                        <select name="direction" class="select" required>
                            <option value="in">Stok Masuk (+)</option>
                            <option value="out">Stok Keluar (−)</option>
                        </select>
                    </div>
                    <div class="field">
                        <label class="field__label">Jumlah <span class="field__req">*</span></label>
                        <input type="number" step="0.001" min="0.001" name="qty" class="input" required>
                    </div>
                </div>

                <div class="field" style="margin-bottom:0">
                    <label class="field__label">Catatan</label>
                    <input type="text" name="note" class="input" placeholder="mis. Penerimaan dari supplier / barang rusak">
                </div>
            </div>

            <div class="modal__foot">
                <button type="button" class="btn btn--ghost" data-modal-close>Batal</button>
                <button type="submit" class="btn btn--primary">Simpan Penyesuaian</button>
            </div>
        </form>
    </div>
</div>
@endallow

@endsection
