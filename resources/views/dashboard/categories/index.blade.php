@extends('layouts.app')

@section('title', 'Kategori')
@section('subtitle', 'Kode kategori ikut membentuk ID produk otomatis')

@section('content')

<div class="page-head">
    <div>
        <h1>Kategori Produk</h1>
        <p class="muted mt-4">{{ $categories->count() }} kategori. Kode singkat dipakai sebagai segmen ID produk.</p>
    </div>
    <button type="button" class="btn btn--primary" data-modal-open="category-modal"
            data-fill='{"name":"","code":"","color":"#4F46E5","sort_order":0,"is_active":true}'
            data-action-url="{{ route('admin.categories.store') }}">
        <x-icon name="plus" size="16"/> Kategori Baru
    </button>
</div>

<div class="card">
    <div class="table-wrap">
        <table class="table">
            <thead>
                <tr>
                    <th>Kategori</th><th>Kode ID</th><th class="t-right">Jumlah Produk</th>
                    <th>Urutan</th><th>Status</th><th></th>
                </tr>
            </thead>
            <tbody>
                @forelse ($categories as $category)
                    @php
                        // Built here rather than with @json inside the
                        // attribute: Blade cannot parse a multi-line array
                        // argument written inline in a directive.
                        $fill = json_encode([
                            'name' => $category->name,
                            'code' => $category->code,
                            'color' => $category->color,
                            'sort_order' => $category->sort_order,
                            'is_active' => $category->is_active,
                        ], JSON_HEX_APOS | JSON_HEX_QUOT);
                    @endphp
                    <tr>
                        <td>
                            <div class="row g-10">
                                <span class="dot" style="width:11px;height:11px;flex-basis:11px;background:{{ $category->color }}"></span>
                                <span class="semi">{{ $category->name }}</span>
                            </div>
                        </td>
                        <td><span class="code-chip">{{ $category->skuCode() }}</span></td>
                        <td class="t-right num">{{ $category->products_count }}</td>
                        <td class="num muted">{{ $category->sort_order }}</td>
                        <td>
                            <span class="badge badge--{{ $category->is_active ? 'ok' : 'neutral' }}">
                                {{ $category->is_active ? 'Aktif' : 'Nonaktif' }}
                            </span>
                        </td>
                        <td class="t-right">
                            <div class="row g-6" style="justify-content:flex-end">
                                <button type="button" class="btn btn--ghost btn--sm" data-modal-open="category-modal"
                                        data-fill='{!! $fill !!}'
                                        data-action-url="{{ route('admin.categories.update', $category) }}">
                                    <x-icon name="edit" size="15"/>
                                </button>
                                <form method="POST" action="{{ route('admin.categories.destroy', $category) }}">
                                    @csrf @method('DELETE')
                                    <button type="submit" class="btn btn--ghost btn--sm bad"
                                            data-confirm="Hapus kategori {{ $category->name }}?">
                                        <x-icon name="trash" size="15"/>
                                    </button>
                                </form>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6">
                        <div class="empty">
                            <div class="empty__icon"><x-icon name="tags" size="24"/></div>
                            <div class="empty__title">Belum ada kategori</div>
                            <div class="empty__text">Kategori membantu mengelompokkan produk dan membentuk ID produk.</div>
                        </div>
                    </td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

{{-- Shared create/edit dialog --}}
<div class="modal" id="category-modal">
    <div class="modal__backdrop"></div>
    <div class="modal__panel modal__panel--narrow">
        <form method="POST" action="{{ route('admin.categories.store') }}">
            @csrf
            {{-- Laravel reads the override, so one form serves both actions. --}}
            <input type="hidden" name="_method" value="PUT" id="category-method" disabled>

            <div class="modal__head">
                <div class="modal__title">Kategori Produk</div>
                <div class="modal__sub">Kode akan muncul di ID produk, mis. KSJ-<b>KOP</b>-2608-0001</div>
            </div>

            <div class="modal__body">
                <div class="field">
                    <label class="field__label">Nama Kategori <span class="field__req">*</span></label>
                    <input type="text" name="name" class="input" required data-autofocus placeholder="mis. Kopi">
                </div>

                <div class="grid grid-2">
                    <div class="field">
                        <label class="field__label">Kode ID</label>
                        <input type="text" name="code" class="input mono" maxlength="8" placeholder="KOP">
                        <span class="field__hint">Huruf/angka saja. Kosong = 3 huruf pertama nama.</span>
                    </div>
                    <div class="field">
                        <label class="field__label">Warna</label>
                        <input type="color" name="color" class="input" style="height:38px;padding:3px" value="#4F46E5">
                    </div>
                </div>

                <div class="field">
                    <label class="field__label">Urutan Tampil</label>
                    <input type="number" name="sort_order" class="input" min="0" value="0">
                </div>

                <label class="switch">
                    <input type="checkbox" name="is_active" value="1" checked>
                    <span class="switch__track"></span>
                    <span class="check__text">Kategori aktif</span>
                </label>
            </div>

            <div class="modal__foot">
                <button type="button" class="btn btn--ghost" data-modal-close>Batal</button>
                <button type="submit" class="btn btn--primary">Simpan</button>
            </div>
        </form>
    </div>
</div>

@push('scripts')
<script>
    // The dialog posts to /categories for a new row and PUTs to
    // /categories/{id} when editing, so the method override follows the URL.
    document.getElementById('category-modal').addEventListener('modal:open', function () {
        var form = this.querySelector('form');
        var method = document.getElementById('category-method');
        method.disabled = form.getAttribute('action').match(/categories\/\d+$/) === null;
    });
</script>
@endpush

@endsection
