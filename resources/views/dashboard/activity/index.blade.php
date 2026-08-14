@extends('layouts.app')

@section('title', 'Log Aktivitas')
@section('subtitle', 'Jejak audit tindakan penting di dashboard dan kasir')

@section('content')

<div class="page-head">
    <div>
        <h1>Log Aktivitas</h1>
        <p class="muted mt-4">Pembatalan transaksi, perubahan stok, pengaturan, dan aktivitas login tercatat di sini.</p>
    </div>

    <form method="GET" class="row g-8" data-auto-submit>
        <select name="action" class="select" style="width:auto;min-width:180px">
            <option value="">Semua aktivitas</option>
            <option value="sale" @selected(request('action') === 'sale')>Transaksi</option>
            <option value="product" @selected(request('action') === 'product')>Produk</option>
            <option value="stock" @selected(request('action') === 'stock')>Stok</option>
            <option value="user" @selected(request('action') === 'user')>Pengguna</option>
            <option value="settings" @selected(request('action') === 'settings')>Pengaturan</option>
            <option value="shift" @selected(request('action') === 'shift')>Shift</option>
            <option value="auth" @selected(request('action') === 'auth')>Login dashboard</option>
            <option value="pos" @selected(request('action') === 'pos')>Login kasir</option>
        </select>
    </form>
</div>

<div class="card">
    <div class="table-wrap">
        <table class="table">
            <thead>
                <tr><th>Waktu</th><th>Pengguna</th><th>Aktivitas</th><th>Keterangan</th><th>Sumber</th><th>IP</th></tr>
            </thead>
            <tbody>
                @forelse ($logs as $log)
                    <tr>
                        <td class="small muted nowrap">{{ $log->created_at->format('d/m/y H:i:s') }}</td>
                        <td>
                            <div class="row g-8">
                                <span class="avatar avatar--sm">{{ $log->user?->initials() ?? '?' }}</span>
                                <span class="small semi">{{ $log->user?->name ?? 'Sistem' }}</span>
                            </div>
                        </td>
                        <td><span class="code-chip">{{ $log->action }}</span></td>
                        <td class="small">{{ $log->description ?? '—' }}</td>
                        <td>
                            <span class="badge badge--{{ $log->guard === 'pos' ? 'emerald' : 'brand' }}">
                                {{ $log->guard === 'pos' ? 'Kasir' : 'Dashboard' }}
                            </span>
                        </td>
                        <td class="tiny subtle mono">{{ $log->ip_address ?? '—' }}</td>
                    </tr>
                @empty
                    <tr><td colspan="6">
                        <div class="empty">
                            <div class="empty__icon"><x-icon name="activity" size="24"/></div>
                            <div class="empty__title">Belum ada aktivitas tercatat</div>
                        </div>
                    </td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{ $logs->links() }}
</div>

@endsection
