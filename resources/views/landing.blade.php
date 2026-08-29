@php
    /**
     * Public front door for pos.aldeftech.com.
     *
     * Its whole job is to send a visitor to the right place: a cashier to the
     * terminal, an owner to the dashboard. Everything else on the page exists
     * to make that choice obvious.
     */
    $store = $tenant?->name ?? 'Kasir POS';
    $signedIn = auth('web')->user();
    $atTill = auth('pos')->user();
@endphp
<!DOCTYPE html>
<html lang="id" data-theme="light">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $store }} · Sistem Kasir &amp; Manajemen Toko</title>

    <meta name="description" content="Sistem kasir dengan terminal berdiri sendiri, dashboard pengelola berbasis peran, stok per outlet, dan laporan lengkap siap ekspor PDF.">

    <script>
        try {
            var t = localStorage.getItem('kasir.theme');
            if (t) document.documentElement.setAttribute('data-theme', t);
        } catch (e) {}
    </script>

    <link rel="stylesheet" href="{{ asset_v('assets/css/app.css') }}">
    <link rel="stylesheet" href="{{ asset_v('assets/css/landing.css') }}">
</head>
<body>

<div class="page">

    {{-- ======================= Header ======================= --}}
    <header class="site-header">
        <div class="site-header__inner">
            <a href="{{ route('landing') }}" class="site-brand">
                <span class="aldef-chip aldef-chip--sm">
                    <img src="{{ asset_v('assets/img/aldef-mark.png') }}" alt="Aldef Tech"
                         width="32" height="32" decoding="async">
                </span>
                <span>
                    <span class="site-brand__name">{{ $store }}</span>
                    <span class="site-brand__sub">Point of Sale by Aldef Tech</span>
                </span>
            </a>

            <nav class="site-nav">
                <a href="#fitur" class="site-nav__link">Fitur</a>
                <a href="#outlet" class="site-nav__link">Multi Outlet</a>
                <a href="#peran" class="site-nav__link">Peran</a>
            </nav>

            <div class="site-header__actions">
                <button type="button" class="btn btn--ghost btn--icon" data-theme-toggle title="Ganti tema">
                    <x-icon name="sun" size="17"/>
                    <span class="sr" data-theme-label>Mode Gelap</span>
                </button>

                <a href="{{ route('pos.login') }}" class="btn btn--outline btn--sm">
                    <x-icon name="scan" size="15"/> Kasir
                </a>

                @if ($signedIn)
                    <a href="{{ route('admin.dashboard') }}" class="btn btn--primary btn--sm">
                        <x-icon name="gauge" size="15"/> Dashboard
                    </a>
                @else
                    <a href="{{ route('admin.login') }}" class="btn btn--primary btn--sm">
                        <x-icon name="gauge" size="15"/> Masuk
                    </a>
                @endif
            </div>
        </div>
    </header>

    {{-- ======================= Hero ======================= --}}
    <section class="hero">
        <div class="hero__inner">
            <span class="hero__badge">
                <span class="dot dot--ok pulse"></span>
                {{ $store }} · Sistem aktif
            </span>

            <h1>Kasir yang <em>tenang</em>,<br>toko yang terkendali.</h1>

            <p class="hero__lede">
                Terminal kasir berdiri sendiri untuk meja depan, dashboard pengelola
                untuk belakang layar. Stok terpisah per outlet, laporan lengkap siap
                diekspor, dan struk yang rapi di setiap transaksi.
            </p>

            <div class="hero__actions">
                <a href="{{ route('pos.login') }}" class="btn btn--primary btn--xl">
                    <x-icon name="scan" size="18"/> Buka Terminal Kasir
                </a>
                <a href="{{ $signedIn ? route('admin.dashboard') : route('admin.login') }}"
                   class="btn btn--outline btn--xl">
                    <x-icon name="gauge" size="18"/>
                    {{ $signedIn ? 'Buka Dashboard' : 'Masuk Dashboard' }}
                </a>
            </div>

            @if ($atTill)
                <p class="hero__note">
                    Anda sedang masuk sebagai <b>{{ $atTill->name }}</b> di terminal kasir.
                </p>
            @else
                <p class="hero__note">
                    Kasir masuk dengan PIN · Pengelola masuk dengan kata sandi
                </p>
            @endif
        </div>
    </section>

    {{-- ======================= Two doors ======================= --}}
    <section class="section section--sunken">
        <div class="section__inner">
            <div class="section__head">
                <div class="section__eyebrow">Dua Pintu Masuk</div>
                <h2 class="section__title">Pilih sesuai peran Anda</h2>
                <p class="section__lede">
                    Keduanya terpisah penuh. Masuk di kasir tidak memberi akses ke dashboard,
                    dan sebaliknya — itulah yang menjaga data toko tetap aman di meja depan.
                </p>
            </div>

            <div class="gate">
                <a href="{{ route('pos.login') }}" class="gate__card gate__card--pos">
                    <div class="gate__icon"><x-icon name="scan" size="26"/></div>
                    <h3 class="gate__title">Terminal Kasir</h3>
                    <p class="gate__text">
                        Untuk operator di meja kasir. Pilih nama, masukkan PIN, buka shift,
                        lalu mulai melayani.
                    </p>

                    <ul class="gate__list">
                        <li><x-icon name="check" size="15"/> Pindai barcode atau ketik nama produk</li>
                        <li><x-icon name="check" size="15"/> Tunai, QRIS, kartu, transfer, hingga split</li>
                        <li><x-icon name="check" size="15"/> Shift &amp; laci kas dengan hitungan selisih</li>
                        <li><x-icon name="check" size="15"/> Cetak struk termal 58/80 mm</li>
                    </ul>

                    <div class="gate__cta" style="color:var(--ok-600)">
                        Masuk kasir <x-icon name="arrow-right" size="16"/>
                    </div>
                </a>

                <a href="{{ $signedIn ? route('admin.dashboard') : route('admin.login') }}" class="gate__card">
                    <div class="gate__icon"><x-icon name="gauge" size="26"/></div>
                    <h3 class="gate__title">Dashboard Pengelola</h3>
                    <p class="gate__text">
                        Untuk Owner &amp; Supervisor. Kelola katalog, stok, cabang, pengguna,
                        dan tinjau seluruh laporan.
                    </p>

                    <ul class="gate__list">
                        <li><x-icon name="check" size="15"/> Produk dengan ID, barcode &amp; QR otomatis</li>
                        <li><x-icon name="check" size="15"/> Stok, restok, dan opname per outlet</li>
                        <li><x-icon name="check" size="15"/> Sepuluh laporan siap ekspor PDF &amp; CSV</li>
                        <li><x-icon name="check" size="15"/> Kontrol peran dan jejak audit</li>
                    </ul>

                    <div class="gate__cta" style="color:var(--brand-600)">
                        {{ $signedIn ? 'Buka dashboard' : 'Masuk dashboard' }}
                        <x-icon name="arrow-right" size="16"/>
                    </div>
                </a>
            </div>
        </div>
    </section>

    {{-- ======================= Features ======================= --}}
    <section class="section" id="fitur">
        <div class="section__inner">
            <div class="section__head">
                <div class="section__eyebrow">Fitur</div>
                <h2 class="section__title">Semua yang dibutuhkan toko harian</h2>
                <p class="section__lede">
                    Dirancang untuk dipakai berjam-jam di meja kasir, bukan hanya untuk didemokan.
                </p>
            </div>

            @php
                $features = [
                    ['barcode', 'ID, Barcode &amp; QR Otomatis', 'Setiap produk baru langsung menerima ID sesuai pola yang Anda atur, lengkap dengan barcode Code 128 atau EAN-13 dan QR siap pindai.'],
                    ['store', 'Stok Per Outlet', 'Setiap cabang memegang stoknya sendiri. Kasir hanya bisa menjual stok cabangnya — tidak akan pernah tertukar.'],
                    ['chart', 'Sepuluh Laporan', 'Ringkasan, produk, kategori, kasir, metode bayar, laba, persediaan, shift, dan pembatalan. Rentang tanggal bebas, ekspor PDF &amp; CSV.'],
                    ['printer', 'Struk Premium', 'Cetak termal 58/80 mm dengan logo dan QR verifikasi, atau invoice A4 dalam bentuk PDF.'],
                    ['wallet', 'Shift &amp; Laci Kas', 'Buka dengan modal awal, tutup dengan hitungan fisik. Selisih kas dihitung otomatis dan tercatat.'],
                    ['shield', 'Kontrol Peran', 'Owner, Supervisor, dan Kasir dengan batas akses tegas. Pembatalan transaksi wajib disetujui lewat PIN.'],
                ];
            @endphp

            <div class="feature-grid">
                @foreach ($features as [$icon, $title, $text])
                    <div class="feature-card">
                        <div class="feature-card__icon"><x-icon :name="$icon" size="21"/></div>
                        <h3 class="feature-card__title">{!! $title !!}</h3>
                        <p class="feature-card__text">{!! $text !!}</p>
                    </div>
                @endforeach
            </div>
        </div>
    </section>

    {{-- ======================= Multi outlet ======================= --}}
    <section class="section section--sunken" id="outlet">
        <div class="section__inner">
            <div class="section__head">
                <div class="section__eyebrow">Multi Outlet</div>
                <h2 class="section__title">Satu toko, banyak cabang</h2>
                <p class="section__lede">
                    Katalog dan harga dipakai bersama. Stok, staf, shift, transaksi, dan laporan
                    berdiri sendiri di tiap cabang.
                </p>
            </div>

            <div class="feature-grid">
                <div class="feature-card">
                    <div class="feature-card__icon"><x-icon name="users" size="21"/></div>
                    <h3 class="feature-card__title">Penempatan staf yang tegas</h3>
                    <p class="feature-card__text">
                        Saat mendaftarkan operator, outlet wajib dipilih dan tidak punya nilai bawaan —
                        supaya tidak ada kasir yang tertempatkan di cabang yang salah.
                    </p>
                </div>
                <div class="feature-card">
                    <div class="feature-card__icon"><x-icon name="receipt" size="21"/></div>
                    <h3 class="feature-card__title">Nomor invoice per cabang</h3>
                    <p class="feature-card__text">
                        Kode outlet menyatu di nomor invoice, mis. <span class="mono">INV-CKN-260815-0001</span>,
                        jadi dua cabang tidak pernah bentrok nomor.
                    </p>
                </div>
                <div class="feature-card">
                    <div class="feature-card__icon"><x-icon name="layers" size="21"/></div>
                    <h3 class="feature-card__title">Lihat satu atau semua</h3>
                    <p class="feature-card__text">
                        Satu pemilih di dashboard menyaring seluruh halaman sekaligus, atau tampilkan
                        gabungan semua cabang lengkap dengan perbandingan performanya.
                    </p>
                </div>
            </div>
        </div>
    </section>

    {{-- ======================= Roles ======================= --}}
    <section class="section" id="peran">
        <div class="section__inner">
            <div class="section__head">
                <div class="section__eyebrow">Peran &amp; Akses</div>
                <h2 class="section__title">Setiap orang melihat secukupnya</h2>
                <p class="section__lede">
                    Batasnya ditegakkan di lapisan data, bukan sekadar disembunyikan dari tampilan.
                </p>
            </div>

            <div class="role-grid">
                @foreach (\App\Support\Role::all() as $role)
                    <div class="role-card">
                        <div class="row g-8 mb-8">
                            <span class="badge badge--{{ $role->badgeColor() }}">{{ $role->label() }}</span>
                        </div>
                        <p class="feature-card__text" style="margin-top:0;min-height:78px">
                            {{ $role->description() }}
                        </p>

                        <ul class="role-card__list">
                            <li>
                                @if ($role->canAccessDashboard())
                                    <x-icon name="check" size="15" style="color:var(--ok-600)"/> Dashboard pengelola
                                @else
                                    <x-icon name="x" size="15" style="color:var(--bad-500)"/> Dashboard pengelola
                                @endif
                            </li>
                            <li>
                                @if ($role->can('report.profit'))
                                    <x-icon name="check" size="15" style="color:var(--ok-600)"/> Lihat modal &amp; laba
                                @else
                                    <x-icon name="x" size="15" style="color:var(--bad-500)"/> Lihat modal &amp; laba
                                @endif
                            </li>
                            <li>
                                @if ($role->can('sale.void'))
                                    <x-icon name="check" size="15" style="color:var(--ok-600)"/> Setujui pembatalan
                                @else
                                    <x-icon name="x" size="15" style="color:var(--bad-500)"/> Setujui pembatalan
                                @endif
                            </li>
                            <li>
                                @if ($role->can('user.manage'))
                                    <x-icon name="check" size="15" style="color:var(--ok-600)"/> Kelola pengguna &amp; outlet
                                @else
                                    <x-icon name="x" size="15" style="color:var(--bad-500)"/> Kelola pengguna &amp; outlet
                                @endif
                            </li>
                        </ul>
                    </div>
                @endforeach
            </div>
        </div>
    </section>

    {{-- ======================= Closing ======================= --}}
    <section class="section" style="padding-top:0">
        <div class="closing">
            <div>
                <h2>Siap melayani pelanggan berikutnya?</h2>
                <p>
                    Buka terminal untuk mulai bertransaksi, atau masuk ke dashboard
                    untuk meninjau penjualan hari ini.
                </p>
            </div>

            <div class="row g-10 wrap">
                <a href="{{ route('pos.login') }}" class="btn btn--lg"
                   style="background:#fff;color:var(--brand-700)">
                    <x-icon name="scan" size="17"/> Terminal Kasir
                </a>
                <a href="{{ $signedIn ? route('admin.dashboard') : route('admin.login') }}"
                   class="btn btn--lg"
                   style="background:rgba(255,255,255,.14);color:#fff;border:1px solid rgba(255,255,255,.28)">
                    <x-icon name="gauge" size="17"/> Dashboard
                </a>
            </div>
        </div>
    </section>

    {{-- ======================= Footer ======================= --}}
    <footer class="site-footer">
        <div class="site-footer__inner">
            <div class="site-footer__grid">
                <div>
                    <a href="{{ route('landing') }}" class="site-brand mb-12">
                        <span class="aldef-chip aldef-chip--sm">
                            <img src="{{ asset_v('assets/img/aldef-mark.png') }}" alt=""
                                 width="32" height="32" loading="lazy" decoding="async">
                        </span>
                        <span>
                            <span class="site-brand__name">{{ $store }}</span>
                            <span class="site-brand__sub">Point of Sale</span>
                        </span>
                    </a>

                    <p class="small muted" style="max-width:320px;line-height:1.65">
                        Sistem kasir dan manajemen toko dengan terminal berdiri sendiri,
                        stok per outlet, dan pelaporan lengkap.
                    </p>
                </div>

                <div>
                    <div class="site-footer__title">Produk</div>
                    <a href="#fitur" class="site-footer__link">Fitur</a>
                    <a href="#outlet" class="site-footer__link">Multi Outlet</a>
                    <a href="#peran" class="site-footer__link">Peran &amp; Akses</a>
                </div>

                <div>
                    <div class="site-footer__title">Akses</div>
                    <a href="{{ route('pos.login') }}" class="site-footer__link">Terminal Kasir</a>
                    <a href="{{ $signedIn ? route('admin.dashboard') : route('admin.login') }}"
                       class="site-footer__link">Dashboard Pengelola</a>
                </div>

                <div>
                    <div class="site-footer__title">Kontak Toko</div>

                    @if ($tenant?->address)
                        <p class="small muted" style="line-height:1.65">
                            {{ $tenant->address }}@if ($tenant->city)<br>{{ $tenant->city }}@endif
                        </p>
                    @endif

                    @if ($tenant?->phone)
                        <a href="tel:{{ preg_replace('/\s+/', '', $tenant->phone) }}" class="site-footer__link">
                            {{ $tenant->phone }}
                        </a>
                    @endif

                    @if ($tenant?->email)
                        <a href="mailto:{{ $tenant->email }}" class="site-footer__link">{{ $tenant->email }}</a>
                    @endif
                </div>
            </div>

            <div class="site-footer__bottom">
                <span>&copy; {{ now()->year }} {{ $tenant?->legal_name ?? $store }}. Seluruh hak cipta dilindungi.</span>

                <span class="aldef-credit">
                    <img src="{{ asset_v('assets/img/aldef-mark.png') }}" alt=""
                         width="16" height="16" loading="lazy" decoding="async">
                    Dikembangkan oleh Aldef Tech
                </span>
            </div>
        </div>
    </footer>
</div>

<script src="{{ asset_v('assets/js/app.js') }}"></script>
</body>
</html>
