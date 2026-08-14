@extends('layouts.app')

@section('title', 'Transaksi ' . $sale->invoice_number)
@section('subtitle', $sale->created_at->translatedFormat('l, d F Y H:i'))

@section('content')

<div class="page-head">
    <div class="row g-12">
        <a href="{{ route('admin.sales.index') }}" class="btn btn--ghost btn--icon"><x-icon name="arrow-left" size="18"/></a>
        <div>
            <h1 class="mono">{{ $sale->invoice_number }}</h1>
            <div class="row g-8 mt-4 wrap">
                <span class="badge badge--{{ $sale->status === 'completed' ? 'ok' : 'bad' }}">
                    {{ $sale->status === 'completed' ? 'Selesai' : 'Dibatalkan' }}
                </span>
                <span class="small muted">Kasir {{ $sale->user?->name }}</span>
                @if ($sale->shift)
                    <span class="small muted">· Shift #{{ $sale->shift_id }}</span>
                @endif
            </div>
        </div>
    </div>

    <div class="row g-8 wrap">
        <a href="{{ route('admin.sales.receipt', $sale) }}" target="_blank" class="btn btn--outline">
            <x-icon name="printer" size="16"/> Cetak Struk
        </a>
        <a href="{{ route('admin.sales.invoice', $sale) }}" class="btn btn--outline">
            <x-icon name="download" size="16"/> Invoice PDF
        </a>
        @allow('sale.void')
            @if (! $sale->isVoided())
                <button type="button" class="btn btn--danger-soft" data-modal-open="void-modal">
                    <x-icon name="x" size="16"/> Batalkan
                </button>
            @endif
        @endallow
    </div>
</div>

@if ($sale->isVoided())
    <div class="alert alert--bad">
        <x-icon name="alert" size="17" class="alert__icon"/>
        <div>
            <div class="semi">Transaksi ini dibatalkan</div>
            <div class="small mt-4">
                Oleh {{ $sale->voidedBy?->name ?? '—' }} pada
                {{ $sale->voided_at?->translatedFormat('d F Y H:i') }} ·
                Alasan: {{ $sale->void_reason }}. Stok sudah dikembalikan.
            </div>
        </div>
    </div>
@endif

<div class="grid grid-2-1">
    <div class="card">
        <div class="card__head"><div class="card__title">Rincian Item</div></div>
        <div class="table-wrap">
            <table class="table">
                <thead>
                    <tr>
                        <th>Produk</th><th class="t-right">Harga</th><th class="t-right">Qty</th>
                        <th class="t-right">Diskon</th><th class="t-right">Subtotal</th>
                        @allow('report.profit')<th class="t-right">Laba</th>@endallow
                    </tr>
                </thead>
                <tbody>
                    @foreach ($sale->items as $item)
                        <tr>
                            <td>
                                <div class="semi">{{ $item->name }}</div>
                                <div class="tiny subtle mono">{{ $item->sku }}</div>
                            </td>
                            <td class="t-right num">{{ money($item->unit_price) }}</td>
                            <td class="t-right num">{{ qty_label($item->qty) }} {{ $item->unit }}</td>
                            <td class="t-right num {{ (float) $item->discount_amount > 0 ? 'ok' : 'subtle' }}">
                                {{ (float) $item->discount_amount > 0 ? money($item->discount_amount) : '—' }}
                            </td>
                            <td class="t-right num semi">{{ money($item->line_total) }}</td>
                            @allow('report.profit')
                                <td class="t-right num {{ $item->profit() >= 0 ? 'ok' : 'bad' }}">{{ money($item->profit()) }}</td>
                            @endallow
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>

    <div class="stack g-16">
        <div class="card">
            <div class="card__head"><div class="card__title">Ringkasan</div></div>
            <div class="card__body">
                <div class="total-row"><span>Subtotal</span><span>{{ money($sale->subtotal) }}</span></div>

                @if ((float) $sale->discount_amount > 0)
                    <div class="total-row total-row--discount">
                        <span>Diskon{{ $sale->discount_type === 'percent' ? ' ('.rtrim(rtrim((string)$sale->discount_value,'0'),'.').'%)' : '' }}</span>
                        <span>− {{ money($sale->discount_amount) }}</span>
                    </div>
                @endif

                @if ((float) $sale->service_charge_amount > 0)
                    <div class="total-row"><span>Biaya layanan</span><span>{{ money($sale->service_charge_amount) }}</span></div>
                @endif

                @if ((float) $sale->tax_amount > 0)
                    <div class="total-row">
                        <span>PPN {{ rtrim(rtrim((string) $sale->tax_percent, '0'), '.') }}%</span>
                        <span>{{ money($sale->tax_amount) }}</span>
                    </div>
                @endif

                @if (abs((float) $sale->rounding_amount) > 0.004)
                    <div class="total-row"><span>Pembulatan</span><span>{{ money($sale->rounding_amount) }}</span></div>
                @endif

                <div class="total-row total-row--grand"><span>Total</span><span>{{ money($sale->total) }}</span></div>

                <div class="divider"></div>

                <div class="total-row"><span>Dibayar</span><span>{{ money($sale->paid_amount) }}</span></div>
                <div class="total-row"><span>Kembalian</span><span>{{ money($sale->change_amount) }}</span></div>

                @allow('report.profit')
                    <div class="divider"></div>
                    <div class="total-row"><span>Modal</span><span>{{ money($sale->cost_total) }}</span></div>
                    <div class="total-row">
                        <span>Laba kotor</span>
                        <span class="{{ (float) $sale->profit >= 0 ? 'ok' : 'bad' }}">{{ money($sale->profit) }}</span>
                    </div>
                @endallow
            </div>
        </div>

        <div class="card">
            <div class="card__head"><div class="card__title">Pembayaran</div></div>
            <div class="card__body">
                @foreach ($sale->payments as $payment)
                    <div class="between" style="padding:9px 0;border-bottom:1px solid var(--border)">
                        <div class="row g-8">
                            <x-icon name="{{ $payment->method === 'cash' ? 'wallet' : 'credit-card' }}" size="16" class="subtle"/>
                            <span class="small semi">{{ $payment->methodLabel() }}</span>
                        </div>
                        <span class="num semi">{{ money($payment->amount) }}</span>
                    </div>
                @endforeach

                <div class="divider"></div>

                <div class="row between small"><span class="muted">Pelanggan</span>
                    <span class="semi">{{ $sale->customer?->name ?? 'Pelanggan Umum' }}</span></div>
                <div class="row between small mt-8"><span class="muted">Jumlah item</span>
                    <span class="semi">{{ $sale->item_count }} baris · {{ qty_label($sale->total_qty) }} unit</span></div>

                @if ($sale->note)
                    <div class="divider"></div>
                    <div class="tiny subtle upper mb-4">Catatan</div>
                    <div class="small muted">{{ $sale->note }}</div>
                @endif
            </div>
        </div>
    </div>
</div>

@allow('sale.void')
@if (! $sale->isVoided())
<div class="modal" id="void-modal">
    <div class="modal__backdrop"></div>
    <div class="modal__panel modal__panel--narrow">
        <form method="POST" action="{{ route('admin.sales.void', $sale) }}">
            @csrf
            <div class="modal__head">
                <div class="modal__title">Batalkan Transaksi</div>
                <div class="modal__sub">Stok akan dikembalikan dan omzet periode ini menyesuaikan.</div>
            </div>
            <div class="modal__body">
                <div class="alert alert--warn">
                    <x-icon name="alert" size="17" class="alert__icon"/>
                    <div>Transaksi {{ $sale->invoice_number }} senilai {{ money($sale->total) }} akan ditandai batal. Data tidak dihapus.</div>
                </div>
                <div class="field" style="margin-bottom:0">
                    <label class="field__label">Alasan Pembatalan <span class="field__req">*</span></label>
                    <input type="text" name="reason" class="input" required data-autofocus
                           placeholder="mis. Salah input produk">
                </div>
            </div>
            <div class="modal__foot">
                <button type="button" class="btn btn--ghost" data-modal-close>Batal</button>
                <button type="submit" class="btn btn--danger">Ya, Batalkan Transaksi</button>
            </div>
        </form>
    </div>
</div>
@endif
@endallow

@endsection
