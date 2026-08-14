@extends('layouts.app')

@section('title', 'Outlet & Cabang')
@section('subtitle', 'Kelola cabang, penempatan staf, dan perbandingan performanya')

@section('content')

<div class="page-head">
    <div>
        <h1>Outlet &amp; Cabang</h1>
        <p class="muted mt-4">
            {{ $outlets->count() }} outlet terdaftar. Stok, shift, dan transaksi tercatat terpisah per outlet.
        </p>
    </div>

    <div class="row g-8 wrap">
        <form method="GET" class="row g-8" data-auto-submit>
            <input type="date" name="from" value="{{ $from }}" class="input" style="width:auto">
            <span class="muted small">s/d</span>
            <input type="date" name="to" value="{{ $to }}" class="input" style="width:auto">
        </form>

        <button type="button" class="btn btn--primary" data-modal-open="outlet-modal"
                data-fill='{"name":"","code":"","address":"","city":"","phone":"","receipt_footer":"","sort_order":0,"is_active":true,"is_default":false}'
                data-action-url="{{ route('admin.outlets.store') }}">
            <x-icon name="plus" size="16"/> Outlet Baru
        </button>
    </div>
</div>

<div class="alert alert--info">
    <x-icon name="alert" size="17" class="alert__icon"/>
    <div>
        Katalog produk dan harga berlaku untuk semua outlet. Yang terpisah per outlet adalah
        <b>stok, staf, shift, transaksi, dan laporan</b>. Gunakan pemilih outlet di bagian atas
        untuk berpindah tampilan.
    </div>
</div>

<div class="grid grid-3 mb-20">
    @foreach ($outlets as $item)
        @php
            $stats = $performance->get($item->id);

            // Built here rather than with @json inside the attribute: Blade
            // cannot parse a multi-line array argument written inline.
            $fill = json_encode([
                'name' => $item->name,
                'code' => $item->code,
                'address' => $item->address,
                'city' => $item->city,
                'phone' => $item->phone,
                'receipt_footer' => $item->receipt_footer,
                'sort_order' => $item->sort_order,
                'is_active' => $item->is_active,
                'is_default' => $item->is_default,
            ], JSON_HEX_APOS | JSON_HEX_QUOT);
        @endphp

        <div class="card card--pad">
            <div class="between mb-12">
                <div class="row g-10" style="min-width:0">
                    <span class="code-chip">{{ $item->code }}</span>
                    <div style="min-width:0">
                        <div class="semi truncate">{{ $item->name }}</div>
                        <div class="tiny subtle truncate">{{ $item->city ?? '—' }}</div>
                    </div>
                </div>

                <div class="row g-6">
                    @if ($item->is_default)
                        <span class="badge badge--brand">Utama</span>
                    @endif
                    <span class="badge badge--{{ $item->is_active ? 'ok' : 'bad' }}">
                        {{ $item->is_active ? 'Aktif' : 'Nonaktif' }}
                    </span>
                </div>
            </div>

            <div class="divider"></div>

            <div class="row between small">
                <span class="muted">Omzet periode</span>
                <span class="semi num">{{ money($stats['revenue'] ?? 0) }}</span>
            </div>
            <div class="row between small mt-8">
                <span class="muted">Transaksi</span>
                <span class="semi num">{{ number_format($stats['transactions'] ?? 0, 0, ',', '.') }}</span>
            </div>
            @allow('report.profit')
                <div class="row between small mt-8">
                    <span class="muted">Laba kotor</span>
                    <span class="semi num">{{ money($stats['profit'] ?? 0) }}</span>
                </div>
            @endallow
            <div class="row between small mt-8">
                <span class="muted">Nilai stok</span>
                <span class="semi num">{{ money($stockValues[$item->id] ?? 0) }}</span>
            </div>
            <div class="row between small mt-8">
                <span class="muted">Staf aktif</span>
                <span class="semi">{{ $item->users_count }} orang</span>
            </div>

            <div class="divider"></div>

            <div class="row g-6">
                <form method="POST" action="{{ route('admin.outlets.switch') }}" class="grow">
                    @csrf
                    <button type="submit" name="outlet_id" value="{{ $item->id }}"
                            class="btn btn--soft btn--sm btn--block">
                        <x-icon name="layers" size="14"/> Lihat Outlet Ini
                    </button>
                </form>

                <button type="button" class="btn btn--outline btn--sm" data-modal-open="outlet-modal"
                        data-fill='{!! $fill !!}'
                        data-action-url="{{ route('admin.outlets.update', $item) }}">
                    <x-icon name="edit" size="14"/>
                </button>

                <form method="POST" action="{{ route('admin.outlets.destroy', $item) }}">
                    @csrf @method('DELETE')
                    <button type="submit" class="btn btn--ghost btn--sm bad"
                            data-confirm="Hapus atau nonaktifkan outlet {{ $item->name }}?">
                        <x-icon name="trash" size="14"/>
                    </button>
                </form>
            </div>
        </div>
    @endforeach
</div>

<div class="card">
    <div class="card__head">
        <div>
            <div class="card__title">Perbandingan Outlet</div>
            <div class="card__sub">
                {{ \Carbon\Carbon::parse($from)->translatedFormat('d M Y') }}
                – {{ \Carbon\Carbon::parse($to)->translatedFormat('d M Y') }}
            </div>
        </div>
    </div>

    <div class="table-wrap">
        <table class="table">
            <thead>
                <tr>
                    <th>Outlet</th><th>Kota</th>
                    <th class="t-right">Transaksi</th><th class="t-right">Omzet</th>
                    <th class="t-right">Rata-rata</th>
                    @allow('report.profit')<th class="t-right">Laba</th>@endallow
                    <th class="t-right">Nilai Stok</th><th class="t-right">Kontribusi</th>
                </tr>
            </thead>
            <tbody>
                @php $totalRevenue = $performance->sum('revenue') ?: 1; @endphp

                @forelse ($outlets as $item)
                    @php $stats = $performance->get($item->id); @endphp
                    <tr>
                        <td>
                            <div class="row g-8">
                                <span class="code-chip">{{ $item->code }}</span>
                                <span class="semi">{{ $item->name }}</span>
                            </div>
                        </td>
                        <td class="small muted">{{ $item->city ?? '—' }}</td>
                        <td class="t-right num">{{ number_format($stats['transactions'] ?? 0, 0, ',', '.') }}</td>
                        <td class="t-right num semi">{{ money($stats['revenue'] ?? 0) }}</td>
                        <td class="t-right num muted">{{ money($stats['average_basket'] ?? 0) }}</td>
                        @allow('report.profit')
                            <td class="t-right num">{{ money($stats['profit'] ?? 0) }}</td>
                        @endallow
                        <td class="t-right num muted">{{ money($stockValues[$item->id] ?? 0) }}</td>
                        <td class="t-right" style="width:130px">
                            @php $share = (($stats['revenue'] ?? 0) / $totalRevenue) * 100; @endphp
                            <div class="meter"><div class="meter__fill" style="width:{{ min(100, $share) }}%"></div></div>
                            <div class="tiny subtle mt-4">{{ percent_label($share) }}</div>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="8">
                        <div class="empty">
                            <div class="empty__icon"><x-icon name="store" size="24"/></div>
                            <div class="empty__title">Belum ada outlet</div>
                        </div>
                    </td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

<div class="modal" id="outlet-modal">
    <div class="modal__backdrop"></div>
    <div class="modal__panel">
        <form method="POST" action="{{ route('admin.outlets.store') }}">
            @csrf
            <input type="hidden" name="_method" value="PUT" id="outlet-method" disabled>

            <div class="modal__head">
                <div class="modal__title">Data Outlet</div>
                <div class="modal__sub">Kode outlet muncul pada nomor invoice, mis. INV-<b>CKN</b>-260814-0001</div>
            </div>

            <div class="modal__body">
                <div class="grid grid-2">
                    <div class="field">
                        <label class="field__label">Nama Outlet <span class="field__req">*</span></label>
                        <input type="text" name="name" class="input" required data-autofocus
                               placeholder="mis. Kopi Senja Kemang">
                    </div>
                    <div class="field">
                        <label class="field__label">Kode <span class="field__req">*</span></label>
                        <input type="text" name="code" class="input mono" maxlength="12" required
                               placeholder="KMG">
                        <span class="field__hint">Huruf/angka, dipakai di nomor invoice.</span>
                    </div>
                </div>

                <div class="field">
                    <label class="field__label">Alamat</label>
                    <textarea name="address" class="textarea"></textarea>
                </div>

                <div class="grid grid-2">
                    <div class="field">
                        <label class="field__label">Kota</label>
                        <input type="text" name="city" class="input">
                    </div>
                    <div class="field">
                        <label class="field__label">Telepon</label>
                        <input type="text" name="phone" class="input">
                    </div>
                </div>

                <div class="field">
                    <label class="field__label">Catatan Kaki Struk Outlet</label>
                    <textarea name="receipt_footer" class="textarea"
                              placeholder="Kosongkan untuk memakai catatan kaki toko"></textarea>
                </div>

                <div class="field">
                    <label class="field__label">Urutan Tampil</label>
                    <input type="number" name="sort_order" class="input" min="0" value="0">
                </div>

                <label class="switch mb-12">
                    <input type="checkbox" name="is_active" value="1" checked>
                    <span class="switch__track"></span>
                    <span class="check__text">Outlet aktif</span>
                </label>

                <label class="switch">
                    <input type="checkbox" name="is_default" value="1">
                    <span class="switch__track"></span>
                    <span>
                        <span class="check__text">Jadikan outlet utama</span>
                        <span class="check__hint">Menjadi pilihan awal untuk penempatan staf dan stok.</span>
                    </span>
                </label>
            </div>

            <div class="modal__foot">
                <button type="button" class="btn btn--ghost" data-modal-close>Batal</button>
                <button type="submit" class="btn btn--primary">Simpan Outlet</button>
            </div>
        </form>
    </div>
</div>

@push('scripts')
<script>
    document.getElementById('outlet-modal').addEventListener('modal:open', function () {
        var form = this.querySelector('form');
        document.getElementById('outlet-method').disabled =
            form.getAttribute('action').match(/outlets\/\d+$/) === null;
    });
</script>
@endpush

@endsection
