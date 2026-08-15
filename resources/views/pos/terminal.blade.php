<!DOCTYPE html>
<html lang="id" data-theme="light">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Kasir · {{ $tenant?->name }}</title>

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

<div class="progress-top"></div>

<div class="pos" id="pos-app">

    {{-- ======================= Header ======================= --}}
    <header class="pos-top">
        <div class="pos-top__store grow">
            <div class="aldef-chip aldef-chip--sm">
                <img src="{{ asset_v('assets/img/aldef-mark.png') }}" alt="Aldef Tech" width="32" height="32" decoding="async">
            </div>
            <div style="min-width:0">
                {{-- The branch, not the business, is what the cashier needs
                     to see: every sale and stock move lands here. --}}
                <div class="pos-top__name truncate">{{ $outlet?->name ?? $tenant?->name }}</div>
                <div class="pos-top__meta truncate">
                    {{ $cashier->name }} · {{ $cashier->role->label() }}
                    @if ($outlet) · <span class="mono">{{ $outlet->code }}</span> @endif
                </div>
            </div>
        </div>

        <div class="pos-shift-chip">
            <span class="dot dot--ok pulse"></span>
            Shift buka {{ $shift->opened_at->format('H:i') }}
        </div>

        <div class="pos-clock" data-clock>--:--:--</div>

        <button type="button" class="btn btn--outline btn--sm" data-open-held>
            <x-icon name="pause" size="15"/>
            Ditahan
            <span class="badge badge--bad" data-held-count style="{{ $heldCount ? '' : 'display:none' }}">{{ $heldCount }}</span>
        </button>

        <div class="dropdown">
            <button type="button" class="btn btn--ghost btn--icon" data-dropdown>
                <x-icon name="menu" size="18"/>
            </button>
            <div class="dropdown__menu">
                <div class="dropdown__label">{{ $cashier->name }}</div>

                <a href="{{ route('pos.profile') }}" class="dropdown__item">
                    <x-icon name="user" size="16"/> Profil Saya
                </a>
                <a href="{{ route('pos.history') }}" class="dropdown__item">
                    <x-icon name="history" size="16"/> Riwayat Transaksi
                </a>
                <a href="{{ route('pos.shift.report', $shift) }}" class="dropdown__item">
                    <x-icon name="file-text" size="16"/> Laporan Shift
                </a>
                <button type="button" class="dropdown__item" data-theme-toggle>
                    <x-icon name="moon" size="16"/> <span data-theme-label>Mode Gelap</span>
                </button>

                {{-- An Owner or Supervisor working the till needs a way back
                     to the back office; a Kasir never sees this. --}}
                @allow('dashboard.access')
                    <div class="dropdown__sep"></div>
                    <a href="{{ route('admin.dashboard') }}" class="dropdown__item">
                        <x-icon name="gauge" size="16"/> Dashboard Pengelola
                    </a>
                @endallow

                <div class="dropdown__sep"></div>

                <a href="{{ route('pos.shift.close') }}" class="dropdown__item">
                    <x-icon name="wallet" size="16"/> Tutup Shift
                </a>

                <form method="POST" action="{{ route('pos.logout') }}">
                    @csrf
                    <button type="submit" class="dropdown__item dropdown__item--danger">
                        <x-icon name="logout" size="16"/> Keluar
                    </button>
                </form>
            </div>
        </div>
    </header>

    {{-- ======================= Body ======================= --}}
    <div class="pos-body">

        {{-- Category rail --}}
        <nav class="pos-rail" data-rail>
            <button type="button" class="pos-cat is-active" data-category="">
                <span class="pos-cat__swatch" style="background:var(--brand-500)"></span>
                Semua
                <span class="pos-cat__count">{{ count($products) }}</span>
            </button>

            @foreach ($categories as $category)
                <button type="button" class="pos-cat" data-category="{{ $category->id }}">
                    <span class="pos-cat__swatch" style="background:{{ $category->color }}"></span>
                    {{ $category->name }}
                </button>
            @endforeach
        </nav>

        {{-- Catalogue --}}
        <section class="pos-catalogue">
            <div class="pos-search">
                <div class="pos-scan">
                    <x-icon name="scan" size="19" class="pos-scan__icon"/>
                    <input type="text" class="input" data-scan autocomplete="off" inputmode="text"
                           placeholder="Pindai barcode / QR, atau ketik nama produk lalu Enter…">
                    <span class="pos-scan__hint kbd">F2</span>
                </div>

                <div class="search" style="width:220px">
                    <x-icon name="search" size="16" class="search__icon"/>
                    <input type="text" class="input" data-search placeholder="Filter produk…" autocomplete="off">
                </div>
            </div>

            <div class="pos-grid" data-grid></div>
        </section>

        {{-- Cart --}}
        <aside class="pos-cart">
            <div class="pos-cart__handle" data-cart-toggle>
                <span class="semi">Keranjang · <span data-cart-count>Kosong</span></span>
                <x-icon name="chevron-down" size="18"/>
            </div>

            <div class="pos-cart__head">
                <div>
                    <div class="semi">Keranjang</div>
                    <div class="tiny subtle" data-cart-count>Kosong</div>
                </div>
                <button type="button" class="btn btn--ghost btn--sm" data-clear-cart>
                    <x-icon name="trash" size="14"/> Kosongkan
                </button>
            </div>

            <div class="pos-cart__customer">
                <x-icon name="user" size="15" class="subtle"/>
                <span class="grow truncate" data-customer-label>Pelanggan Umum</span>
                <span class="tiny subtle">{{ $todayCount }} trx hari ini</span>
            </div>

            <div class="pos-cart__lines" data-lines></div>

            <div class="pos-cart__totals" data-totals></div>

            <div class="pos-cart__actions">
                <div class="pos-cart__actions-row">
                    <button type="button" class="btn btn--outline btn--sm" data-hold>
                        <x-icon name="pause" size="14"/> Tahan
                    </button>
                    <a href="{{ route('pos.history') }}" class="btn btn--outline btn--sm">
                        <x-icon name="history" size="14"/> Riwayat
                    </a>
                    <a href="{{ route('pos.shift.close') }}" class="btn btn--outline btn--sm">
                        <x-icon name="wallet" size="14"/> Tutup
                    </a>
                </div>

                <button type="button" class="btn btn--primary btn--xl btn--block" data-pay disabled>
                    <x-icon name="wallet" size="19"/>
                    Bayar
                    <span class="kbd" style="background:rgba(255,255,255,.2);border-color:rgba(255,255,255,.3);color:#fff">F4</span>
                </button>
            </div>
        </aside>
    </div>

    {{-- ======================= Payment dialog ======================= --}}
    <div class="modal" id="payment-modal">
        <div class="modal__backdrop"></div>
        <div class="modal__panel modal__panel--wide">
            <div class="modal__head">
                <div class="modal__title">Pembayaran</div>
                <div class="modal__sub">Pilih metode, masukkan nominal, lalu konfirmasi.</div>
            </div>

            <div class="modal__body">
                <div class="pay-grid">
                    {{-- Left: amount entry.
                         The keypad is four rows, not five: "Hapus" sits beside
                         the amount field and "+ Split" moved next to the tender
                         list it actually feeds, which is what lets the whole
                         dialog clear a laptop screen without scrolling. --}}
                    <div>
                        <div class="pay-due mb-12">
                            <div class="pay-due__label">Total Tagihan</div>
                            <div class="pay-due__value" data-due>—</div>
                            <div class="pay-due__rest">
                                Sisa yang harus dibayar: <b data-outstanding>—</b>
                            </div>
                        </div>

                        <label class="field__label mb-6" style="display:block">Nominal Diterima</label>

                        <div class="pay-amount-row mb-10">
                            <input type="text" class="pay-amount" data-amount readonly placeholder="0">
                            <button type="button" class="pay-clear" data-key="clear" title="Hapus nominal">C</button>
                        </div>

                        <div class="keypad">
                            @foreach ([1,2,3,4,5,6,7,8,9] as $digit)
                                <button type="button" data-key="{{ $digit }}">{{ $digit }}</button>
                            @endforeach
                            <button type="button" data-key="000">000</button>
                            <button type="button" data-key="0">0</button>
                            <button type="button" data-key="back">⌫</button>
                        </div>
                    </div>

                    {{-- Right: method, tenders, change --}}
                    <div class="pay-side">
                        <label class="field__label mb-6" style="display:block">Metode Pembayaran</label>
                        <div class="method-grid mb-12">
                            @foreach ($paymentMethods as $value => $label)
                                <button type="button" class="method {{ $value === 'cash' ? 'is-active' : '' }}"
                                        data-method="{{ $value }}">
                                    <x-icon name="{{ $value === 'cash' ? 'wallet' : ($value === 'qris' ? 'qr' : 'credit-card') }}" size="18"/>
                                    {{ $label }}
                                </button>
                            @endforeach
                        </div>

                        {{-- Quick amounts live beside the methods rather than
                             under the keypad: it keeps the tall left column
                             short enough for the dialog to fit one screen,
                             and it only applies to cash anyway. --}}
                        <div data-cash-only>
                            <label class="field__label mb-6" style="display:block">Nominal Cepat</label>
                            <div class="quick-cash mb-12">
                                <button type="button" data-exact>Uang Pas</button>
                                @foreach ([20000, 50000, 100000, 150000, 200000] as $amount)
                                    <button type="button" data-quick="{{ $amount }}">
                                        {{ number_format($amount / 1000, 0, ',', '.') }}rb
                                    </button>
                                @endforeach
                            </div>
                        </div>

                        <div class="between mb-6">
                            <label class="field__label" style="margin:0">Pembayaran Diterima</label>
                            <button type="button" class="btn btn--outline btn--sm" data-add-tender
                                    title="Simpan nominal ini lalu bayar sisanya dengan metode lain">
                                <x-icon name="plus" size="13"/> Split
                            </button>
                        </div>

                        <div data-tenders class="stack g-6 mb-12"></div>

                        <div class="change-box" data-change-box>
                            <div class="change-box__label" data-change-label>Kembalian</div>
                            <div class="change-box__value" data-change-value>—</div>
                        </div>

                        <p class="tiny subtle mt-10" style="line-height:1.5">
                            Bayar gabungan: masukkan nominal, tekan <b>Split</b>, ganti metode, lalu masukkan sisanya.
                        </p>
                    </div>
                </div>
            </div>

            <div class="modal__foot">
                <button type="button" class="btn btn--ghost" data-modal-close>Batal</button>
                <button type="button" class="btn btn--success btn--lg" data-confirm-pay>
                    <x-icon name="check" size="18"/> Selesaikan Transaksi
                </button>
            </div>
        </div>
    </div>

    {{-- ======================= Success dialog ======================= --}}
    <div class="modal" id="done-modal">
        <div class="modal__backdrop"></div>
        <div class="modal__panel modal__panel--narrow">
            <div class="modal__body">
                <div class="done">
                    <div class="done__check"><x-icon name="check" size="38" stroke="3"/></div>
                    <h2>Transaksi Berhasil</h2>
                    <p class="muted mt-4 mono" data-done-invoice>—</p>

                    <div class="done__change">
                        <div class="tiny subtle upper">Kembalian</div>
                        <div class="done__change-value" data-done-change>—</div>
                        <div class="small muted mt-8">Total belanja <b data-done-total>—</b></div>
                    </div>

                    <div class="stack g-8">
                        <a href="#" target="_blank" class="btn btn--outline btn--block" data-print-receipt>
                            <x-icon name="printer" size="17"/> Cetak Ulang Struk
                        </a>
                        <button type="button" class="btn btn--primary btn--lg btn--block" data-new-sale>
                            <x-icon name="plus" size="17"/> Transaksi Baru
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- ======================= Held orders dialog ======================= --}}
    <div class="modal" id="held-modal">
        <div class="modal__backdrop"></div>
        <div class="modal__panel modal__panel--narrow">
            <div class="modal__head">
                <div class="modal__title">Transaksi Ditahan</div>
                <div class="modal__sub">Lanjutkan keranjang yang sebelumnya diparkir.</div>
            </div>
            <div class="modal__body" data-held-list></div>
            <div class="modal__foot">
                <button type="button" class="btn btn--ghost" data-modal-close>Tutup</button>
            </div>
        </div>
    </div>
</div>

<script>
    // Everything the terminal needs to price a cart client-side. The server
    // recalculates on checkout, so this is for display only.
    window.POS_CONFIG = {
        cashierId: {{ $cashier->id }},
        currencySymbol: @json($tenant?->currency_symbol ?? 'Rp'),
        taxEnabled: {{ $tenant?->tax_enabled ? 'true' : 'false' }},
        taxPercent: {{ (float) ($tenant?->tax_percent ?? 0) }},
        taxInclusive: {{ $tenant?->tax_inclusive ? 'true' : 'false' }},
        serviceChargePercent: {{ (float) ($tenant?->service_charge_percent ?? 0) }},
        roundingMode: @json($tenant?->rounding_mode ?? 'none'),
        allowNegativeStock: {{ $tenant?->allow_negative_stock ? 'true' : 'false' }},
        autoPrint: true,
        csrf: @json(csrf_token()),
        paymentMethods: @json($paymentMethods),
        products: @json($products),
        urls: {
            lookup: @json(route('pos.lookup')),
            products: @json(route('pos.products')),
            checkout: @json(route('pos.checkout')),
            hold: @json(route('pos.hold')),
            heldList: @json(route('pos.hold.list')),
            customers: @json(route('pos.customers')),
        },
    };
</script>

<script src="{{ asset_v('assets/js/app.js') }}"></script>
<script src="{{ asset_v('assets/js/pos.js') }}"></script>

</body>
</html>
