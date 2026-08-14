<!DOCTYPE html>
<html lang="id" data-theme="light">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Masuk Dashboard · {{ $tenant?->name ?? config('app.name') }}</title>
    <link rel="stylesheet" href="{{ asset_v('assets/css/app.css') }}">
    <link rel="stylesheet" href="{{ asset_v('assets/css/pos.css') }}">
</head>
<body>
<div class="pos-auth">
    <div class="pos-auth__card">
        <aside class="pos-auth__aside">
            <div>
                <img src="{{ asset_v('assets/img/aldef-logo.png') }}" alt="Aldef Tech" width="168" height="155" decoding="async" class="aldef-lockup mb-20">

                <div class="row g-10 mb-24">
                    <div class="aldef-chip aldef-chip--sm" style="background:rgba(255,255,255,.12);box-shadow:inset 0 0 0 1px rgba(255,255,255,.16)">
                        <x-icon name="store" size="16"/>
                    </div>
                    <div>
                        <div style="font-weight:660">{{ $tenant?->name ?? 'Kasir POS' }}</div>
                        <div style="font-size:11.5px;opacity:.7">Dashboard Pengelola</div>
                    </div>
                </div>

                <h2>Kendali penuh atas<br>operasional toko Anda.</h2>
                <p>Masuk untuk mengelola katalog, memantau stok, dan meninjau laporan penjualan.</p>
            </div>

            <div>
                <div class="pos-auth__feature">
                    <div class="pos-auth__feature-icon"><x-icon name="chart" size="16"/></div>
                    <div>
                        <div style="font-weight:600">Laporan lengkap</div>
                        <div style="opacity:.7;font-size:12px">Rentang tanggal bebas, ekspor PDF &amp; CSV.</div>
                    </div>
                </div>
                <div class="pos-auth__feature">
                    <div class="pos-auth__feature-icon"><x-icon name="shield" size="16"/></div>
                    <div>
                        <div style="font-weight:600">Akses berbasis peran</div>
                        <div style="opacity:.7;font-size:12px">Kasir tidak dapat membuka halaman ini.</div>
                    </div>
                </div>
                <div class="pos-auth__feature">
                    <div class="pos-auth__feature-icon"><x-icon name="barcode" size="16"/></div>
                    <div>
                        <div style="font-weight:600">ID produk otomatis</div>
                        <div style="opacity:.7;font-size:12px">Pola ID, barcode, dan QR dapat diatur sendiri.</div>
                    </div>
                </div>
            </div>
        </aside>

        <div class="pos-auth__form">
            {{-- Repeated here because the branded panel is hidden on phones. --}}
            <img src="{{ asset_v('assets/img/aldef-logo.png') }}" alt="Aldef Tech" width="168" height="155" decoding="async"
                 class="aldef-lockup aldef-lockup--sm mb-16 aldef-lockup--mobile">

            <h1 style="font-size:23px">Masuk Dashboard</h1>
            <p class="muted mt-4" style="font-size:13.5px">Khusus Owner dan Supervisor.</p>

            @if (session('error'))
                <div class="alert alert--bad mt-20">
                    <x-icon name="alert" size="17" class="alert__icon"/>
                    <div>{{ session('error') }}</div>
                </div>
            @endif

            @if (session('status'))
                <div class="alert alert--ok mt-20">
                    <x-icon name="check-circle" size="17" class="alert__icon"/>
                    <div>{{ session('status') }}</div>
                </div>
            @endif

            @error('login')
                <div class="alert alert--bad mt-20">
                    <x-icon name="alert" size="17" class="alert__icon"/>
                    <div>{{ $message }}</div>
                </div>
            @enderror

            <form method="POST" action="{{ route('admin.login.attempt') }}" class="mt-20">
                @csrf

                <div class="field">
                    <label class="field__label" for="login">Email atau Username</label>
                    <input type="text" id="login" name="login" value="{{ old('login') }}"
                           class="input @error('login') is-error @enderror"
                           placeholder="owner" autocomplete="username" autofocus required>
                </div>

                <div class="field">
                    <label class="field__label" for="password">Kata Sandi</label>
                    <input type="password" id="password" name="password"
                           class="input @error('password') is-error @enderror"
                           placeholder="••••••••" autocomplete="current-password" required>
                    @error('password') <span class="field__error">{{ $message }}</span> @enderror
                </div>

                <label class="check mb-20">
                    <input type="checkbox" name="remember" value="1">
                    <span class="check__text">Ingat saya di perangkat ini</span>
                </label>

                <button type="submit" class="btn btn--primary btn--lg btn--block">
                    <x-icon name="lock" size="16"/>
                    Masuk
                </button>
            </form>

            <div class="divider"></div>

            <div class="between">
                <span class="small muted">Anda seorang kasir?</span>
                <a href="{{ route('pos.login') }}" class="btn btn--outline btn--sm">
                    <x-icon name="scan" size="15"/>
                    Terminal Kasir
                </a>
            </div>
        </div>
    </div>
</div>
</body>
</html>
