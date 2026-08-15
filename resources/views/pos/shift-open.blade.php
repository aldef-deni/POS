<!DOCTYPE html>
<html lang="id" data-theme="light">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Buka Shift · {{ $tenant?->name }}</title>
    <link rel="stylesheet" href="{{ asset_v('assets/css/app.css') }}">
    <link rel="stylesheet" href="{{ asset_v('assets/css/pos.css') }}">
</head>
<body>

<div class="pos-auth">
    <div class="pos-auth__card" style="max-width:520px;grid-template-columns:1fr">
        <div class="pos-auth__form">
            <div class="row g-12 mb-20">
                <span class="avatar avatar--lg">
                    @if ($cashier->avatarUrl())
                        <img src="{{ $cashier->avatarUrl() }}" alt="">
                    @else
                        {{ $cashier->initials() }}
                    @endif
                </span>
                <div>
                    <h1 style="font-size:20px">Halo, {{ explode(' ', $cashier->name)[0] }}</h1>
                    <p class="muted" style="font-size:13px">{{ $cashier->role->label() }} · {{ $tenant?->name }}</p>
                </div>
            </div>

            <div class="alert alert--info">
                <x-icon name="wallet" size="17" class="alert__icon"/>
                <div>
                    Hitung uang di laci sebelum mulai. Angka ini menjadi dasar perhitungan selisih kas saat tutup shift.
                </div>
            </div>

            @if ($errors->any())
                <div class="alert alert--bad">
                    <x-icon name="alert" size="17" class="alert__icon"/>
                    <div>{{ $errors->first() }}</div>
                </div>
            @endif

            @if ($lastShift?->closed_at)
                <div class="card card--flat mb-16" style="background:var(--bg-sunken)">
                    <div class="card__body card__body--tight">
                        <div class="tiny subtle upper mb-4">Shift terakhir Anda</div>
                        <div class="row between small">
                            <span class="muted">{{ $lastShift->closed_at->translatedFormat('d M Y H:i') }}</span>
                            <span class="semi">{{ money($lastShift->total_sales) }} · {{ $lastShift->total_transactions }} trx</span>
                        </div>
                    </div>
                </div>
            @endif

            <form method="POST" action="{{ route('pos.shift.open.store') }}">
                @csrf

                <div class="field">
                    <label class="field__label" for="opening_cash">Modal Awal Laci <span class="field__req">*</span></label>
                    <div class="input-group">
                        <span class="input-group__addon">{{ $tenant?->currency_symbol ?? 'Rp' }}</span>
                        <input type="number" step="0.01" min="0" id="opening_cash" name="opening_cash"
                               class="input" style="font-size:19px;font-weight:650;text-align:right"
                               value="{{ old('opening_cash', 0) }}" required autofocus>
                    </div>
                </div>

                <div class="quick-cash mb-16">
                    @foreach ([100000, 200000, 300000, 500000, 750000, 1000000] as $amount)
                        <button type="button" onclick="document.getElementById('opening_cash').value={{ $amount }}">
                            {{ number_format($amount / 1000, 0, ',', '.') }}rb
                        </button>
                    @endforeach
                </div>

                <div class="field">
                    <label class="field__label" for="opening_note">Catatan (opsional)</label>
                    <input type="text" id="opening_note" name="opening_note" class="input"
                           placeholder="mis. Pecahan 50rb sebanyak 6 lembar">
                </div>

                <button type="submit" class="btn btn--primary btn--xl btn--block">
                    <x-icon name="check" size="18"/> Buka Shift &amp; Mulai
                </button>
            </form>

            <div class="divider"></div>

            {{-- Owner/Supervisor may have signed in here only to check the
                 till; don't strand them on the shift screen. --}}
            @allow('dashboard.access')
                <a href="{{ route('admin.dashboard') }}" class="btn btn--outline btn--block mb-12">
                    <x-icon name="gauge" size="16"/> Buka Dashboard Pengelola
                </a>
            @endallow

            <a href="{{ route('pos.profile') }}" class="btn btn--ghost btn--block btn--sm mb-8">
                <x-icon name="user" size="15"/> Profil Saya
            </a>

            <form method="POST" action="{{ route('pos.logout') }}" class="t-center">
                @csrf
                <button type="submit" class="btn btn--ghost btn--sm">
                    <x-icon name="logout" size="15"/> Keluar sebagai {{ $cashier->name }}
                </button>
            </form>
        </div>
    </div>
</div>

<script src="{{ asset_v('assets/js/app.js') }}"></script>
</body>
</html>
