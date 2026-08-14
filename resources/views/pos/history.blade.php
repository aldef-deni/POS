<!DOCTYPE html>
<html lang="id" data-theme="light">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Riwayat Transaksi · Kasir</title>

    <script>
        try {
            var t = localStorage.getItem('kasir.theme');
            if (t) document.documentElement.setAttribute('data-theme', t);
        } catch (e) {}
    </script>

    <link rel="stylesheet" href="{{ asset_v('assets/css/app.css') }}">
    <link rel="stylesheet" href="{{ asset_v('assets/css/pos.css') }}">
</head>
<body @if(session('status')) data-flash="{{ session('status') }}" data-flash-type="ok"
      @elseif(session('error')) data-flash="{{ session('error') }}" data-flash-type="bad" @endif>

<header class="pos-top">
    <a href="{{ route('pos.index') }}" class="btn btn--ghost btn--icon"><x-icon name="arrow-left" size="18"/></a>

    <div class="grow">
        <div class="pos-top__name">Riwayat Transaksi Hari Ini</div>
        <div class="pos-top__meta">{{ $cashier->name }} · {{ now()->translatedFormat('l, d F Y') }}</div>
    </div>

    @if ($shift)
        <div class="pos-shift-chip">
            <span class="dot dot--ok pulse"></span>
            {{ money($shift->total_sales) }} · {{ $shift->total_transactions }} trx
        </div>
    @endif

    <a href="{{ route('pos.index') }}" class="btn btn--primary btn--sm">
        <x-icon name="plus" size="15"/> Transaksi Baru
    </a>
</header>

<div class="content" style="max-width:1100px">
    <div class="card">
        <div class="table-wrap">
            <table class="table">
                <thead>
                    <tr>
                        <th>Waktu</th><th>Invoice</th><th>Pelanggan</th>
                        <th class="t-right">Item</th><th>Pembayaran</th>
                        <th class="t-right">Total</th><th>Status</th><th></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($sales as $sale)
                        <tr>
                            <td class="small muted nowrap">{{ $sale->created_at->format('H:i') }}</td>
                            <td class="mono semi">{{ $sale->invoice_number }}</td>
                            <td class="small muted">{{ $sale->customer?->name ?? 'Umum' }}</td>
                            <td class="t-right num">{{ $sale->item_count }}</td>
                            <td class="small">
                                @foreach ($sale->payments as $payment)
                                    <span class="badge badge--neutral">{{ $payment->methodLabel() }}</span>
                                @endforeach
                            </td>
                            <td class="t-right num semi">{{ money($sale->total) }}</td>
                            <td>
                                <span class="badge badge--{{ $sale->status === 'completed' ? 'ok' : 'bad' }}">
                                    {{ $sale->status === 'completed' ? 'Selesai' : 'Dibatalkan' }}
                                </span>
                            </td>
                            <td class="t-right">
                                <div class="row g-6" style="justify-content:flex-end">
                                    <a href="{{ route('pos.receipt', ['sale' => $sale, 'auto' => 0, 'reprint' => 1]) }}"
                                       target="_blank" class="btn btn--ghost btn--sm" title="Cetak ulang struk">
                                        <x-icon name="printer" size="15"/>
                                    </a>

                                    @if (! $sale->isVoided())
                                        <button type="button" class="btn btn--ghost btn--sm bad" title="Batalkan"
                                                data-modal-open="void-modal"
                                                data-fill='@json(["sale_label" => $sale->invoice_number])'
                                                data-action-url="{{ route('pos.void', $sale) }}">
                                            <x-icon name="x" size="15"/>
                                        </button>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="8">
                            <div class="empty">
                                <div class="empty__icon"><x-icon name="receipt" size="24"/></div>
                                <div class="empty__title">Belum ada transaksi hari ini</div>
                                <div class="empty__text">Transaksi yang Anda proses akan muncul di sini.</div>
                            </div>
                        </td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        {{ $sales->links() }}
    </div>
</div>

{{-- Void requires a supervisor's PIN: a cashier cannot approve their own. --}}
<div class="modal" id="void-modal">
    <div class="modal__backdrop"></div>
    <div class="modal__panel modal__panel--narrow">
        <form method="POST" action="#">
            @csrf
            <div class="modal__head">
                <div class="modal__title">Batalkan Transaksi</div>
                <div class="modal__sub">Persetujuan Owner atau Supervisor diperlukan.</div>
            </div>

            <div class="modal__body">
                <input type="hidden" name="sale_label">

                <div class="alert alert--warn">
                    <x-icon name="alert" size="17" class="alert__icon"/>
                    <div>Stok akan dikembalikan dan transaksi ditandai batal. Data tidak dihapus.</div>
                </div>

                <div class="field">
                    <label class="field__label">Alasan Pembatalan <span class="field__req">*</span></label>
                    <input type="text" name="reason" class="input" required data-autofocus
                           placeholder="mis. Salah input produk">
                </div>

                <div class="field">
                    <label class="field__label">Penyetuju <span class="field__req">*</span></label>
                    <select name="approver_id" class="select" required>
                        <option value="">— Pilih Owner / Supervisor —</option>
                        @foreach (\App\Models\User::whereIn('role', ['Owner', 'Supervisor'])->where('is_active', true)->whereNotNull('pos_pin')->orderBy('name')->get() as $approver)
                            <option value="{{ $approver->id }}">{{ $approver->name }} ({{ $approver->role->value }})</option>
                        @endforeach
                    </select>
                </div>

                <div class="field" style="margin-bottom:0">
                    <label class="field__label">PIN Penyetuju <span class="field__req">*</span></label>
                    <input type="password" name="pin" class="input mono" inputmode="numeric"
                           minlength="4" maxlength="8" required placeholder="4–8 digit">
                    <span class="field__hint">PIN kasir milik Owner/Supervisor yang menyetujui.</span>
                </div>
            </div>

            <div class="modal__foot">
                <button type="button" class="btn btn--ghost" data-modal-close>Batal</button>
                <button type="submit" class="btn btn--danger">Batalkan Transaksi</button>
            </div>
        </form>
    </div>
</div>

<script src="{{ asset_v('assets/js/app.js') }}"></script>
</body>
</html>
