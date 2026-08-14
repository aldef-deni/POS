@extends('layouts.app')

@section('title', 'Pelanggan')
@section('subtitle', 'Basis pelanggan dan poin loyalitas')

@section('content')

<div class="page-head">
    <div>
        <h1>Pelanggan</h1>
        <p class="muted mt-4">{{ $customers->total() }} pelanggan terdaftar. Poin bertambah otomatis setiap transaksi.</p>
    </div>
    <button type="button" class="btn btn--primary" data-modal-open="customer-modal"
            data-fill='{"name":"","phone":"","email":"","address":"","code":"","is_member":true}'
            data-action-url="{{ route('admin.customers.store') }}">
        <x-icon name="plus" size="16"/> Pelanggan Baru
    </button>
</div>

<div class="card mb-20">
    <div class="card__body card__body--tight">
        <form method="GET" class="search" data-search-form>
            <x-icon name="search" size="16" class="search__icon"/>
            <input type="text" name="q" value="{{ $filters['q'] ?? '' }}" class="input"
                   placeholder="Cari nama, nomor telepon, atau kode member…">
        </form>
    </div>
</div>

<div class="card">
    <div class="table-wrap">
        <table class="table">
            <thead>
                <tr>
                    <th>Pelanggan</th><th>Kontak</th><th class="t-right">Kunjungan</th>
                    <th class="t-right">Total Belanja</th><th class="t-right">Poin</th>
                    <th>Terakhir</th><th></th>
                </tr>
            </thead>
            <tbody>
                @forelse ($customers as $customer)
                    @php
                        // Blade cannot parse a multi-line array written
                        // inline in @json, so the payload is built here.
                        $fill = json_encode([
                            'name' => $customer->name,
                            'phone' => $customer->phone,
                            'email' => $customer->email,
                            'address' => $customer->address,
                            'code' => $customer->code,
                            'is_member' => $customer->is_member,
                        ], JSON_HEX_APOS | JSON_HEX_QUOT);
                    @endphp
                    <tr>
                        <td>
                            <div class="row g-10">
                                <span class="avatar avatar--sm">{{ mb_substr($customer->name, 0, 1) }}</span>
                                <div>
                                    <div class="semi">{{ $customer->name }}</div>
                                    <div class="tiny subtle">
                                        {{ $customer->code ?? '—' }}
                                        @if ($customer->is_member) · <span class="ok">Member</span> @endif
                                    </div>
                                </div>
                            </div>
                        </td>
                        <td class="small">
                            {{ $customer->phone ?? '—' }}
                            @if ($customer->email)<div class="tiny subtle">{{ $customer->email }}</div>@endif
                        </td>
                        <td class="t-right num">{{ $customer->visit_count }}</td>
                        <td class="t-right num semi">{{ money($customer->total_spent) }}</td>
                        <td class="t-right num">
                            <span class="badge badge--brand">{{ number_format($customer->points, 0, ',', '.') }}</span>
                        </td>
                        <td class="small muted nowrap">{{ $customer->last_visit_at?->format('d/m/Y') ?? '—' }}</td>
                        <td class="t-right">
                            <div class="row g-6" style="justify-content:flex-end">
                                <button type="button" class="btn btn--ghost btn--sm" data-modal-open="customer-modal"
                                        data-fill='{!! $fill !!}'
                                        data-action-url="{{ route('admin.customers.update', $customer) }}">
                                    <x-icon name="edit" size="15"/>
                                </button>
                                <form method="POST" action="{{ route('admin.customers.destroy', $customer) }}">
                                    @csrf @method('DELETE')
                                    <button type="submit" class="btn btn--ghost btn--sm bad"
                                            data-confirm="Hapus pelanggan {{ $customer->name }}?">
                                        <x-icon name="trash" size="15"/>
                                    </button>
                                </form>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="7">
                        <div class="empty">
                            <div class="empty__icon"><x-icon name="users" size="24"/></div>
                            <div class="empty__title">Belum ada pelanggan</div>
                            <div class="empty__text">Kasir juga dapat menambah pelanggan langsung dari terminal.</div>
                        </div>
                    </td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{ $customers->links() }}
</div>

<div class="modal" id="customer-modal">
    <div class="modal__backdrop"></div>
    <div class="modal__panel modal__panel--narrow">
        <form method="POST" action="{{ route('admin.customers.store') }}">
            @csrf
            <input type="hidden" name="_method" value="PUT" id="customer-method" disabled>

            <div class="modal__head">
                <div class="modal__title">Data Pelanggan</div>
            </div>

            <div class="modal__body">
                <div class="field">
                    <label class="field__label">Nama <span class="field__req">*</span></label>
                    <input type="text" name="name" class="input" required data-autofocus>
                </div>
                <div class="grid grid-2">
                    <div class="field">
                        <label class="field__label">Telepon</label>
                        <input type="text" name="phone" class="input">
                    </div>
                    <div class="field">
                        <label class="field__label">Kode Member</label>
                        <input type="text" name="code" class="input mono">
                    </div>
                </div>
                <div class="field">
                    <label class="field__label">Email</label>
                    <input type="email" name="email" class="input">
                </div>
                <div class="field">
                    <label class="field__label">Alamat</label>
                    <textarea name="address" class="textarea"></textarea>
                </div>
                <label class="switch">
                    <input type="checkbox" name="is_member" value="1" checked>
                    <span class="switch__track"></span>
                    <span class="check__text">Terdaftar sebagai member</span>
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
    document.getElementById('customer-modal').addEventListener('modal:open', function () {
        var form = this.querySelector('form');
        document.getElementById('customer-method').disabled =
            form.getAttribute('action').match(/customers\/\d+$/) === null;
    });
</script>
@endpush

@endsection
