<!DOCTYPE html>
<html lang="id" data-theme="light">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $tenant?->name ?? config('app.name') }} · Sistem Kasir</title>
    <link rel="stylesheet" href="{{ asset('assets/css/app.css') }}">
    <style>
        .hero {
            min-height: 100vh;
            display: flex; align-items: center; justify-content: center;
            padding: 40px 20px;
            background:
                radial-gradient(1200px 640px at 8% -10%, rgba(99,102,241,.18), transparent 58%),
                radial-gradient(1000px 560px at 100% 105%, rgba(16,185,129,.14), transparent 55%),
                var(--bg);
        }
        .hero__inner { width: 100%; max-width: 1060px; }
        .hero__badge {
            display: inline-flex; align-items: center; gap: 7px;
            padding: 5px 13px; border-radius: var(--r-full);
            background: var(--surface); border: 1px solid var(--border);
            font-size: 12px; font-weight: 600; color: var(--text-muted);
            box-shadow: var(--sh-xs);
        }
        .hero h1 { font-size: 48px; letter-spacing: -.04em; margin: 20px 0 14px; line-height: 1.08; }
        .hero p.lede { font-size: 16.5px; color: var(--text-muted); max-width: 620px; line-height: 1.65; }
        .gate { display: grid; grid-template-columns: repeat(2, 1fr); gap: 16px; margin-top: 36px; }
        .gate__card {
            display: block; padding: 26px;
            background: var(--surface); border: 1px solid var(--border);
            border-radius: var(--r-xl); box-shadow: var(--sh-md);
            color: var(--text);
            transition: transform .16s ease, box-shadow .16s ease, border-color .16s ease;
        }
        .gate__card:hover { transform: translateY(-3px); box-shadow: var(--sh-lg); border-color: var(--brand-400); color: var(--text); }
        .gate__icon {
            width: 46px; height: 46px; border-radius: var(--r-md);
            display: flex; align-items: center; justify-content: center;
            background: var(--brand-50); color: var(--brand-600); margin-bottom: 15px;
        }
        .gate__card--pos .gate__icon { background: var(--ok-50); color: var(--ok-600); }
        .feature-row { display: grid; grid-template-columns: repeat(4, 1fr); gap: 14px; margin-top: 40px; }
        .feature { font-size: 13px; }
        .feature__title { font-weight: 620; margin-bottom: 3px; display: flex; align-items: center; gap: 7px; }
        @media (max-width: 860px) {
            .hero h1 { font-size: 34px; }
            .gate, .feature-row { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>
<div class="hero">
    <div class="hero__inner">
        <img src="{{ asset('assets/img/aldef-logo.png') }}" alt="Aldef Tech"
             style="width:100%;max-width:168px;height:auto;margin-bottom:22px">

        <div>
            <span class="hero__badge">
                <span class="dot dot--ok"></span>
                {{ $tenant?->name ?? 'Kasir POS' }} · Sistem aktif
            </span>
        </div>

        <h1>Point of Sale<br>yang rapi, cepat, dan siap pakai.</h1>

        <p class="lede">
            Terminal kasir berdiri sendiri dengan login operator terpisah, dashboard pengelola
            dengan kontrol peran berlapis, dan pelaporan lengkap yang bisa diekspor ke PDF.
        </p>

        <div class="gate">
            <a href="{{ route('pos.login') }}" class="gate__card gate__card--pos">
                <div class="gate__icon"><x-icon name="scan" size="24"/></div>
                <h3>Terminal Kasir</h3>
                <p class="muted mt-4" style="font-size:13.5px">
                    Untuk operator di meja kasir. Pilih nama Anda, masukkan PIN, buka shift, lalu mulai melayani.
                </p>
                <div class="row g-6 mt-16 semi" style="color:var(--ok-600);font-size:13px">
                    Buka kasir <x-icon name="arrow-right" size="15"/>
                </div>
            </a>

            <a href="{{ route('admin.login') }}" class="gate__card">
                <div class="gate__icon"><x-icon name="gauge" size="24"/></div>
                <h3>Dashboard Pengelola</h3>
                <p class="muted mt-4" style="font-size:13.5px">
                    Untuk Owner &amp; Supervisor. Kelola produk, stok, pengguna, dan lihat laporan lengkap.
                </p>
                <div class="row g-6 mt-16 semi" style="color:var(--brand-600);font-size:13px">
                    Masuk dashboard <x-icon name="arrow-right" size="15"/>
                </div>
            </a>
        </div>

        <div class="feature-row">
            <div class="feature">
                <div class="feature__title"><x-icon name="barcode" size="15"/> Barcode &amp; QR otomatis</div>
                <div class="muted">Setiap produk baru langsung punya ID, barcode, dan QR siap cetak.</div>
            </div>
            <div class="feature">
                <div class="feature__title"><x-icon name="shield" size="15"/> Tiga peran terpisah</div>
                <div class="muted">Owner, Supervisor, dan Kasir dengan batas akses yang tegas.</div>
            </div>
            <div class="feature">
                <div class="feature__title"><x-icon name="file-text" size="15"/> Laporan &amp; ekspor PDF</div>
                <div class="muted">Sepuluh jenis laporan dengan rentang tanggal bebas.</div>
            </div>
            <div class="feature">
                <div class="feature__title"><x-icon name="printer" size="15"/> Struk premium</div>
                <div class="muted">Cetak termal 58/80mm maupun invoice A4.</div>
            </div>
        </div>

        <div class="mt-24">
            <span class="aldef-credit">
                <img src="{{ asset('assets/img/aldef-mark.png') }}" alt="">
                Dikembangkan oleh Aldef Tech
            </span>
        </div>
    </div>
</div>
</body>
</html>
