@extends('layouts.app')

@section('title', 'Stok Opname')
@section('subtitle', 'Cocokkan stok sistem dengan hitungan fisik di gudang')

@section('content')

<div class="page-head">
    <div class="row g-12">
        <a href="{{ route('admin.stock.index') }}" class="btn btn--ghost btn--icon"><x-icon name="arrow-left" size="18"/></a>
        <div>
            <h1>Stok Opname</h1>
            <p class="muted mt-4">Isi kolom hitungan fisik. Baris yang dikosongkan akan dilewati.</p>
        </div>
    </div>

    <form method="GET" class="search" style="min-width:240px" data-search-form>
        <x-icon name="search" size="16" class="search__icon"/>
        <input type="text" name="q" value="{{ $filters['q'] ?? '' }}" class="input" placeholder="Cari produk…">
    </form>
</div>

<form method="POST" action="{{ route('admin.stock.opname.store') }}">
    @csrf

    <div class="card mb-16">
        <div class="card__body card__body--tight">
            <div class="field" style="margin-bottom:0">
                <label class="field__label">Catatan Opname</label>
                <input type="text" name="note" class="input"
                       placeholder="mis. Opname bulanan Agustus 2026" value="Stok opname {{ now()->translatedFormat('d F Y') }}">
            </div>
        </div>
    </div>

    <div class="card">
        <div class="table-wrap">
            <table class="table">
                <thead>
                    <tr>
                        <th>Produk</th><th>Kategori</th>
                        <th class="t-right">Stok Sistem</th>
                        <th class="t-right" style="width:170px">Hitungan Fisik</th>
                        <th class="t-right">Selisih</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($products as $product)
                        <tr>
                            <td>
                                <div class="semi">{{ $product->name }}</div>
                                <div class="tiny subtle mono">{{ $product->sku }}</div>
                            </td>
                            <td class="small muted">{{ $product->category?->name ?? '—' }}</td>
                            <td class="t-right num semi" data-system="{{ (float) $product->stock }}">
                                {{ qty_label($product->stock) }} {{ $product->unit }}
                            </td>
                            <td class="t-right">
                                <input type="number" step="0.001" min="0"
                                       name="counted[{{ $product->id }}]" class="input t-right"
                                       placeholder="{{ qty_label($product->stock) }}"
                                       data-counted="{{ $product->id }}">
                            </td>
                            <td class="t-right num" data-diff="{{ $product->id }}">
                                <span class="subtle">—</span>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="5">
                            <div class="empty">
                                <div class="empty__title">Tidak ada produk untuk diopname</div>
                                <div class="empty__text">Hanya produk aktif dengan pelacakan stok yang ditampilkan.</div>
                            </div>
                        </td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if ($products->isNotEmpty())
            <div class="card__foot between">
                <span class="small muted">
                    <span class="semi" data-changed-count>0</span> produk memiliki selisih
                </span>
                <button type="submit" class="btn btn--primary"
                        data-confirm="Simpan hasil opname? Stok akan disesuaikan dan tercatat di riwayat.">
                    <x-icon name="check" size="16"/> Simpan Hasil Opname
                </button>
            </div>
        @endif
    </div>
</form>

@push('scripts')
<script>
    // Live difference column so an operator sees the variance as they count.
    document.querySelectorAll('[data-counted]').forEach(function (input) {
        input.addEventListener('input', function () {
            var row = input.closest('tr');
            var system = parseFloat(row.querySelector('[data-system]').getAttribute('data-system'));
            var cell = row.querySelector('[data-diff="' + input.getAttribute('data-counted') + '"]');

            if (input.value === '') {
                cell.innerHTML = '<span class="subtle">—</span>';
            } else {
                var diff = parseFloat(input.value) - system;
                var cls = diff === 0 ? 'subtle' : (diff > 0 ? 'ok' : 'bad');
                cell.innerHTML = '<span class="semi ' + cls + '">' + (diff > 0 ? '+' : '') + diff + '</span>';
            }

            var changed = 0;
            document.querySelectorAll('[data-counted]').forEach(function (i) {
                if (i.value === '') return;
                var s = parseFloat(i.closest('tr').querySelector('[data-system]').getAttribute('data-system'));
                if (parseFloat(i.value) !== s) changed++;
            });
            document.querySelector('[data-changed-count]').textContent = changed;
        });
    });
</script>
@endpush

@endsection
