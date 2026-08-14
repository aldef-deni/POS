@extends('layouts.app')

@section('title', 'Shift Kasir')
@section('subtitle', 'Riwayat buka/tutup laci beserta selisih kas')

@section('content')

<div class="page-head">
    <div>
        <h1>Shift Kasir</h1>
        <p class="muted mt-4">Setiap transaksi terikat pada satu shift agar kas dapat direkonsiliasi.</p>
    </div>

    <form method="GET" class="row g-8 wrap" data-auto-submit>
        <input type="date" name="from" value="{{ $from }}" class="input" style="width:auto">
        <span class="muted small">s/d</span>
        <input type="date" name="to" value="{{ $to }}" class="input" style="width:auto">
        <select name="status" class="select" style="width:auto">
            <option value="">Semua</option>
            <option value="open" @selected(request('status') === 'open')>Terbuka</option>
            <option value="closed" @selected(request('status') === 'closed')>Ditutup</option>
        </select>
        <button type="submit" class="btn btn--outline"><x-icon name="filter" size="15"/></button>
    </form>
</div>

<div class="card">
    <div class="table-wrap">
        <table class="table">
            <thead>
                <tr>
                    <th>Kasir</th><th>Buka</th><th>Tutup</th><th>Durasi</th>
                    <th class="t-right">Trx</th><th class="t-right">Modal Awal</th>
                    <th class="t-right">Tunai</th><th class="t-right">Non-Tunai</th>
                    <th class="t-right">Selisih Kas</th><th></th>
                </tr>
            </thead>
            <tbody>
                @forelse ($shifts as $shift)
                    <tr>
                        <td>
                            <div class="row g-10">
                                <span class="avatar avatar--sm">{{ $shift->user?->initials() }}</span>
                                <div>
                                    <div class="semi small">{{ $shift->user?->name }}</div>
                                    <div class="tiny subtle">
                                        @if ($shift->isOpen())
                                            <span class="ok">● Sedang berjalan</span>
                                        @else
                                            Ditutup oleh {{ $shift->closedBy?->name ?? '—' }}
                                        @endif
                                    </div>
                                </div>
                            </div>
                        </td>
                        <td class="small muted nowrap">{{ $shift->opened_at->format('d/m/y H:i') }}</td>
                        <td class="small muted nowrap">{{ $shift->closed_at?->format('d/m/y H:i') ?? '—' }}</td>
                        <td class="small muted">{{ $shift->durationLabel() }}</td>
                        <td class="t-right num">{{ $shift->total_transactions }}</td>
                        <td class="t-right num muted">{{ money($shift->opening_cash) }}</td>
                        <td class="t-right num">{{ money($shift->cash_sales) }}</td>
                        <td class="t-right num">{{ money($shift->non_cash_sales) }}</td>
                        <td class="t-right num semi">
                            @if ($shift->isOpen())
                                <span class="subtle">—</span>
                            @elseif (abs((float) $shift->cash_variance) < 0.01)
                                <span class="badge badge--ok">Pas</span>
                            @else
                                <span class="{{ (float) $shift->cash_variance > 0 ? 'warn' : 'bad' }}">
                                    {{ (float) $shift->cash_variance > 0 ? '+' : '' }}{{ money($shift->cash_variance) }}
                                </span>
                            @endif
                        </td>
                        <td class="t-right">
                            <a href="{{ route('admin.shifts.show', $shift) }}" class="btn btn--ghost btn--sm">
                                Detail <x-icon name="chevron-right" size="13"/>
                            </a>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="10">
                        <div class="empty">
                            <div class="empty__icon"><x-icon name="clock" size="24"/></div>
                            <div class="empty__title">Belum ada shift</div>
                            <div class="empty__text">Shift tercatat saat kasir membuka laci di terminal.</div>
                        </div>
                    </td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{ $shifts->links() }}
</div>

@endsection
