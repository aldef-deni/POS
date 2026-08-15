@extends('layouts.app')

@section('title', 'Restok Produk')
@section('subtitle', 'Catat barang masuk untuk banyak produk sekaligus')

@section('content')

<div class="page-head">
    <div class="row g-12">
        <a href="{{ route('admin.stock.index') }}" class="btn btn--ghost btn--icon">
            <x-icon name="arrow-left" size="18"/>
        </a>
        <div>
            <h1>Restok Produk</h1>
            <p class="muted mt-4">
                @if ($activeOutlet)
                    Menambah stok di <b>{{ $activeOutlet->name }}</b>.
                    Isi kolom <b>Jumlah Masuk</b>, lalu simpan sekali di bawah.
                @else
                    <span class="bad">Pilih satu outlet</span> pada pemilih di kanan atas — stok selalu masuk ke satu cabang.
                @endif
            </p>
        </div>
    </div>

    <a href="{{ route('admin.stock.opname') }}" class="btn btn--outline">
        <x-icon name="layers" size="16"/> Stok Opname
    </a>
</div>

{{-- Three numbers that say what needs attention right now --}}
<div class="grid grid-4 mb-20">
    <div class="stat {{ $counts['habis'] > 0 ? 'stat--accent' : '' }}">
        <div class="stat__label">Stok Habis</div>
        <div class="stat__value {{ $counts['habis'] > 0 ? 'bad' : '' }}">{{ $counts['habis'] }}</div>
        <div class="stat__meta">Tidak bisa dijual sekarang</div>
    </div>
    <div class="stat">
        <div class="stat__label">Stok Menipis</div>
        <div class="stat__value {{ $counts['menipis'] > 0 ? 'warn' : '' }}">{{ $counts['menipis'] }}</div>
        <div class="stat__meta">Sudah di bawah batas minimum</div>
    </div>
    <div class="stat">
        <div class="stat__label">Stok Aman</div>
        <div class="stat__value ok">{{ $counts['aman'] }}</div>
        <div class="stat__meta">Belum perlu diisi ulang</div>
    </div>
    <div class="stat">
        <div class="stat__label">Nilai Stok</div>
        <div class="stat__value" style="font-size:21px">{{ money($stockValue) }}</div>
        <div class="stat__meta">Dihitung dari harga modal</div>
    </div>
</div>

@if (! $activeOutlet)
    <div class="alert alert--warn">
        <x-icon name="alert" size="17" class="alert__icon"/>
        <div>
            <div class="semi">Pilih outlet terlebih dahulu</div>
            <div class="small mt-4">
                Setiap cabang punya stoknya sendiri, jadi barang masuk harus punya tujuan yang jelas.
                Gunakan pemilih outlet di kanan atas, lalu kembali ke halaman ini.
            </div>
        </div>
    </div>
@endif

<form method="POST" action="{{ route('admin.stock.restock.store') }}" data-restock-form>
    @csrf

    <div class="card mb-16">
        <div class="card__body card__body--tight">
            <div class="row g-8 wrap">
                {{-- Filters keep their own GET form so typing a quantity is
                     never lost by an accidental search submit. --}}
                <div class="btn-group">
                    <a href="{{ route('admin.stock.restock', ['show' => 'need', 'q' => $filters['q']]) }}"
                       class="btn btn--sm {{ $filters['show'] !== 'all' ? 'is-active' : '' }}">
                        Perlu Restok ({{ $counts['habis'] + $counts['menipis'] }})
                    </a>
                    <a href="{{ route('admin.stock.restock', ['show' => 'all', 'q' => $filters['q']]) }}"
                       class="btn btn--sm {{ $filters['show'] === 'all' ? 'is-active' : '' }}">
                        Semua Produk
                    </a>
                </div>

                <div class="grow" style="min-width:200px">
                    <div class="search">
                        <x-icon name="search" size="16" class="search__icon"/>
                        <input type="text" class="input" placeholder="Cari produk…"
                               value="{{ $filters['q'] }}" data-restock-search>
                    </div>
                </div>

                {{-- Fills every row so its shelf reaches a multiple of the
                     product's own minimum — stated plainly so the number is
                     never a mystery. --}}
                <div class="row g-6">
                    <span class="small muted nowrap">Isi otomatis sampai</span>
                    <input type="number" min="1" max="20" step="1" value="2" class="input"
                           style="width:64px;text-align:center" data-fill-factor>
                    <span class="small muted nowrap">× minimum</span>
                    <button type="button" class="btn btn--soft btn--sm" data-fill-suggested>
                        <x-icon name="refresh" size="14"/> Terapkan
                    </button>
                </div>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="table-wrap">
            <table class="table">
                <thead>
                    <tr>
                        <th>Produk</th>
                        <th>Status</th>
                        <th class="t-right">Stok Sekarang</th>
                        <th class="t-right">Minimum</th>
                        <th class="t-right" style="width:150px">Jumlah Masuk</th>
                        <th class="t-right">Stok Setelah</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($products as $product)
                        <tr data-row
                            data-name="{{ Str::lower($product->name.' '.$product->sku) }}"
                            data-onhand="{{ $product->on_hand }}"
                            data-min="{{ $product->minimum }}">
                            <td>
                                <div class="semi">{{ $product->name }}</div>
                                <div class="tiny subtle">
                                    <span class="mono">{{ $product->sku }}</span>
                                    · {{ $product->category?->name ?? 'Tanpa kategori' }}
                                    · modal {{ money($product->cost_price) }}
                                </div>
                            </td>
                            <td>
                                @if ($product->stock_status === 'habis')
                                    <span class="badge badge--bad">Habis</span>
                                @elseif ($product->stock_status === 'menipis')
                                    <span class="badge badge--warn">Menipis</span>
                                @else
                                    <span class="badge badge--ok">Aman</span>
                                @endif
                            </td>
                            <td class="t-right num semi {{ $product->on_hand <= 0 ? 'bad' : '' }}">
                                {{ qty_label($product->on_hand) }} {{ $product->unit }}
                            </td>
                            <td class="t-right num subtle">{{ qty_label($product->minimum) }}</td>
                            <td class="t-right">
                                <input type="number" step="0.001" min="0"
                                       name="qty[{{ $product->id }}]" class="input t-right"
                                       placeholder="0" data-qty
                                       @disabled(! $activeOutlet)>
                            </td>
                            <td class="t-right num" data-after>
                                <span class="subtle">{{ qty_label($product->on_hand) }}</span>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="6">
                            <div class="empty">
                                <div class="empty__icon" style="background:var(--ok-50);color:var(--ok-600)">
                                    <x-icon name="check-circle" size="24"/>
                                </div>
                                <div class="empty__title">Semua stok aman</div>
                                <div class="empty__text">
                                    Tidak ada produk yang habis atau menipis di outlet ini.
                                    Pilih <b>Semua Produk</b> bila tetap ingin menambah stok.
                                </div>
                            </div>
                        </td></tr>
                    @endforelse

                    {{-- Shown by the search filter when nothing matches. --}}
                    <tr data-no-match hidden>
                        <td colspan="6">
                            <div class="empty">
                                <div class="empty__title">Produk tidak ditemukan</div>
                                <div class="empty__text">Coba kata kunci lain.</div>
                            </div>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        @if ($products->isNotEmpty())
            <div class="card__foot">
                <div class="row between wrap g-12">
                    <div class="grow" style="min-width:240px">
                        <input type="text" name="note" class="input"
                               placeholder="Catatan, mis. Faktur supplier #1234 (opsional)">
                    </div>

                    <div class="row g-16">
                        <div class="t-right">
                            <div class="tiny subtle upper">Akan dicatat</div>
                            <div class="semi">
                                <span data-filled-count>0</span> produk ·
                                <span data-filled-qty>0</span> unit
                            </div>
                        </div>

                        <button type="submit" class="btn btn--primary btn--lg" data-submit
                                @disabled(! $activeOutlet)>
                            <x-icon name="check" size="17"/>
                            Simpan Restok{{ $activeOutlet ? ' · '.$activeOutlet->code : '' }}
                        </button>
                    </div>
                </div>
            </div>
        @endif
    </div>
</form>

@push('scripts')
<script>
(function () {
    var form = document.querySelector('[data-restock-form]');
    if (!form) return;

    var rows = Array.prototype.slice.call(form.querySelectorAll('[data-row]'));
    var noMatch = form.querySelector('[data-no-match]');
    var countEl = form.querySelector('[data-filled-count]');
    var qtyEl = form.querySelector('[data-filled-qty]');
    var submit = form.querySelector('[data-submit]');

    function num(value) {
        var n = parseFloat(value);
        return isNaN(n) ? 0 : n;
    }

    function trim(value) {
        // Drop trailing zeros so "12.000" reads as "12".
        return String(Math.round(value * 1000) / 1000);
    }

    /** Live preview of the resulting shelf, plus the footer tally. */
    function refresh() {
        var filled = 0;
        var total = 0;

        rows.forEach(function (row) {
            var onHand = num(row.getAttribute('data-onhand'));
            var input = row.querySelector('[data-qty]');
            var after = row.querySelector('[data-after]');
            var add = num(input.value);

            if (add > 0) {
                filled++;
                total += add;
                after.innerHTML = '<span class="semi ok">' + trim(onHand + add) + '</span>';
                row.style.background = 'var(--ok-50)';
            } else {
                after.innerHTML = '<span class="subtle">' + trim(onHand) + '</span>';
                row.style.background = '';
            }
        });

        countEl.textContent = filled;
        qtyEl.textContent = trim(total);

        if (submit && !submit.hasAttribute('disabled')) {
            submit.classList.toggle('is-disabled', filled === 0);
        }
    }

    form.addEventListener('input', function (event) {
        if (event.target.hasAttribute('data-qty')) refresh();
    });

    // Suggest enough to reach a multiple of each product's own minimum.
    var fill = form.querySelector('[data-fill-suggested]');
    if (fill) {
        fill.addEventListener('click', function () {
            var factor = num(form.querySelector('[data-fill-factor]').value) || 2;

            rows.forEach(function (row) {
                if (row.hidden) return;

                var onHand = num(row.getAttribute('data-onhand'));
                var minimum = num(row.getAttribute('data-min'));
                var target = minimum * factor;
                var need = target - onHand;

                // A product with no minimum set has nothing to aim at.
                if (minimum > 0 && need > 0) {
                    row.querySelector('[data-qty]').value = trim(need);
                }
            });

            refresh();
            window.posToast('Jumlah masuk diisi sampai ' + factor + '× stok minimum.');
        });
    }

    // Filter in place: typing must never discard quantities already entered.
    var search = form.querySelector('[data-restock-search]');
    if (search) {
        search.addEventListener('input', function () {
            var term = search.value.trim().toLowerCase();
            var visible = 0;

            rows.forEach(function (row) {
                var match = !term || row.getAttribute('data-name').indexOf(term) !== -1;
                row.hidden = !match;
                if (match) visible++;
            });

            if (noMatch) noMatch.hidden = visible > 0;
        });
    }

    form.addEventListener('submit', function (event) {
        if (num(countEl.textContent) === 0) {
            event.preventDefault();
            window.posToast('Isi kolom "Jumlah Masuk" minimal satu produk.', 'bad');
        }
    });

    refresh();
})();
</script>
@endpush

@endsection
