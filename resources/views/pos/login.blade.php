<!DOCTYPE html>
<html lang="id" data-theme="light">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Masuk Kasir · {{ $tenant?->name ?? config('app.name') }}</title>
    <link rel="stylesheet" href="{{ asset_v('assets/css/app.css') }}">
    <link rel="stylesheet" href="{{ asset_v('assets/css/pos.css') }}">
</head>
<body>

<div class="pos-auth">
    <div class="pos-auth__card">
        <aside class="pos-auth__aside">
            <div class="t-center">
                <img src="{{ asset_v('assets/img/aldef-logo.png') }}" alt="Aldef Tech" width="168" height="155" decoding="async"
                     class="aldef-lockup" style="margin-inline:auto">

                <div class="store-name">
                    {{ $tenant?->name ?? 'Kasir POS' }}
                    <span class="store-name__rule"></span>
                </div>
            </div>

            <div>
                <div class="pos-auth__feature">
                    <div class="pos-auth__feature-icon"><x-icon name="scan" size="16"/></div>
                    <div>
                        <div style="font-weight:600">Pindai barcode langsung</div>
                        <div style="opacity:.7;font-size:12px">Scanner mengetik kode lalu Enter — produk langsung masuk.</div>
                    </div>
                </div>
                <div class="pos-auth__feature">
                    <div class="pos-auth__feature-icon"><x-icon name="wallet" size="16"/></div>
                    <div>
                        <div style="font-weight:600">Shift &amp; laci kas</div>
                        <div style="opacity:.7;font-size:12px">Buka modal awal, tutup dengan hitungan fisik.</div>
                    </div>
                </div>
                <div class="pos-auth__feature">
                    <div class="pos-auth__feature-icon"><x-icon name="printer" size="16"/></div>
                    <div>
                        <div style="font-weight:600">Struk premium</div>
                        <div style="opacity:.7;font-size:12px">Cetak termal 58/80 mm lengkap dengan QR verifikasi.</div>
                    </div>
                </div>
            </div>
        </aside>

        <div class="pos-auth__form">
            {{-- Repeated because the branded panel is hidden on phones. --}}
            <img src="{{ asset_v('assets/img/aldef-logo.png') }}" alt="Aldef Tech" width="168" height="155" decoding="async"
                 class="aldef-lockup aldef-lockup--sm mb-16 aldef-lockup--mobile">

            <h1 style="font-size:22px">Masuk Kasir</h1>
            <p class="muted mt-4" style="font-size:13px">Pilih operator, lalu masukkan PIN Anda.</p>

            @if (session('error'))
                <div class="alert alert--bad mt-16">
                    <x-icon name="alert" size="17" class="alert__icon"/>
                    <div>{{ session('error') }}</div>
                </div>
            @endif

            @if (session('status'))
                <div class="alert alert--ok mt-16">
                    <x-icon name="check-circle" size="17" class="alert__icon"/>
                    <div>{{ session('status') }}</div>
                </div>
            @endif

            @if ($errors->any())
                <div class="alert alert--bad mt-16">
                    <x-icon name="alert" size="17" class="alert__icon"/>
                    <div>{{ $errors->first() }}</div>
                </div>
            @endif

            @if ($operators->isEmpty())
                {{-- Without a cashier holding a PIN there is no way in, so
                     say plainly what the Owner needs to do. --}}
                <div class="empty mt-16" style="padding:32px 8px">
                    <div class="empty__icon"><x-icon name="users" size="24"/></div>
                    <div class="empty__title">Belum ada kasir yang siap bertugas</div>
                    <div class="empty__text">
                        Terminal ini hanya dapat dibuka oleh pengguna berperan <b>Kasir</b> yang sudah memiliki PIN.
                        Minta Owner menambahkan kasir dan mengatur PIN-nya melalui
                        <b>Dashboard → Pengguna &amp; Peran</b>.
                    </div>
                    <a href="{{ route('admin.login') }}" class="btn btn--primary mt-16">
                        <x-icon name="gauge" size="16"/> Buka Dashboard
                    </a>
                </div>
            @else
                <form method="POST" action="{{ route('pos.login.attempt') }}" id="pin-form" class="mt-20">
                    @csrf
                    <input type="hidden" name="user_id" id="pin-user">
                    <input type="hidden" name="pin" id="pin-value">

                    <div class="tiny subtle upper mb-8 t-center">Pilih operator</div>
                    <div class="operator-strip mb-16">
                        @foreach ($operators as $operator)
                            <button type="button" class="operator" data-operator="{{ $operator->id }}">
                                <span class="avatar">
                                    @if ($operator->avatarUrl())
                                        <img src="{{ $operator->avatarUrl() }}" alt="">
                                    @else
                                        {{ $operator->initials() }}
                                    @endif
                                </span>
                                <span class="operator__name">{{ $operator->name }}</span>
                            </button>
                        @endforeach
                    </div>

                    <div class="pin-display" data-pin-display></div>

                    <div class="t-center tiny subtle mb-12" data-pin-hint>
                        Pilih operator terlebih dahulu
                    </div>

                    <div class="keypad">
                        @foreach ([1,2,3,4,5,6,7,8,9] as $digit)
                            <button type="button" data-pin-key="{{ $digit }}">{{ $digit }}</button>
                        @endforeach
                        <button type="button" class="is-clear" data-pin-key="clear">C</button>
                        <button type="button" data-pin-key="0">0</button>
                        <button type="button" data-pin-key="back">⌫</button>
                    </div>

                    <button type="submit" class="btn btn--primary btn--lg btn--block mt-16" id="pin-submit" disabled>
                        <x-icon name="lock" size="16"/> Masuk dengan PIN
                    </button>
                </form>
            @endif

            <div class="divider"></div>

            <div class="between">
                <span class="small muted">Pengelola toko?</span>
                <a href="{{ route('admin.login') }}" class="btn btn--outline btn--sm">
                    <x-icon name="gauge" size="15"/> Dashboard
                </a>
            </div>
        </div>
    </div>
</div>

<script src="{{ asset_v('assets/js/app.js') }}"></script>
<script>
    (function () {
        var form = document.getElementById('pin-form');
        if (!form) return;

        var MIN_PIN = 4;
        var MAX_PIN = 8;

        var pin = '';
        var selected = null;

        var userField = document.getElementById('pin-user');
        var pinField = document.getElementById('pin-value');
        var submit = document.getElementById('pin-submit');
        var display = document.querySelector('[data-pin-display]');
        var hint = document.querySelector('[data-pin-hint]');

        function refresh() {
            // Slots grow with the entry: PIN length varies per operator and
            // theirs is hashed, so a fixed number of dots would mislead.
            var slots = Math.max(MIN_PIN, pin.length);
            var dots = '';

            for (var i = 0; i < slots; i++) {
                dots += '<span class="pin-dot' + (i < pin.length ? ' is-filled' : '') + '"></span>';
            }

            display.innerHTML = dots;

            pinField.value = pin;
            userField.value = selected || '';

            submit.disabled = !(selected && pin.length >= MIN_PIN);

            if (!selected) {
                hint.textContent = 'Pilih operator terlebih dahulu';
            } else if (pin.length < MIN_PIN) {
                hint.textContent = 'Masukkan PIN Anda';
            } else {
                hint.textContent = 'Tekan Masuk atau Enter untuk melanjutkan';
            }
        }

        document.querySelectorAll('[data-operator]').forEach(function (button) {
            button.addEventListener('click', function () {
                document.querySelectorAll('[data-operator]').forEach(function (b) {
                    b.classList.remove('is-active');
                });
                button.classList.add('is-active');
                selected = button.getAttribute('data-operator');
                pin = '';
                refresh();
            });
        });

        document.querySelectorAll('[data-pin-key]').forEach(function (key) {
            key.addEventListener('click', function () {
                var value = key.getAttribute('data-pin-key');

                if (value === 'clear') pin = '';
                else if (value === 'back') pin = pin.slice(0, -1);
                else if (pin.length < MAX_PIN) pin += value;

                refresh();
            });
        });

        // Physical keyboards and numeric keypads work too. There is no
        // auto-submit: PIN length varies, so the operator decides when done.
        document.addEventListener('keydown', function (event) {
            if (/^[0-9]$/.test(event.key)) {
                if (pin.length < MAX_PIN) { pin += event.key; refresh(); }
            } else if (event.key === 'Backspace') {
                event.preventDefault();
                pin = pin.slice(0, -1);
                refresh();
            } else if (event.key === 'Enter' && !submit.disabled) {
                event.preventDefault();
                form.submit();
            }
        });

        // Preselect when there is only one cashier to choose from.
        var only = document.querySelectorAll('[data-operator]');
        if (only.length === 1) only[0].click();

        refresh();
    })();
</script>

</body>
</html>
