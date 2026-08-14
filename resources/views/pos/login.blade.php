<!DOCTYPE html>
<html lang="id" data-theme="light">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Masuk Kasir · {{ $tenant?->name ?? config('app.name') }}</title>
    <link rel="stylesheet" href="{{ asset('assets/css/app.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/css/pos.css') }}">
</head>
<body>

<div class="pos-auth">
    <div class="pos-auth__card">
        <aside class="pos-auth__aside">
            <div>
                <div class="row g-10 mb-24">
                    <div class="brand-mark" style="background:rgba(255,255,255,.16);box-shadow:none">
                        {{ mb_substr($tenant?->name ?? 'KP', 0, 2) }}
                    </div>
                    <div>
                        <div style="font-weight:660">{{ $tenant?->name ?? 'Kasir POS' }}</div>
                        <div style="font-size:11.5px;opacity:.7">Terminal Kasir</div>
                    </div>
                </div>

                <h2>Siap melayani<br>pelanggan berikutnya.</h2>
                <p>Masuk dengan akun operator Anda. Terminal ini berdiri sendiri dan tidak memerlukan akses dashboard.</p>
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
            <h1 style="font-size:23px">Masuk Kasir</h1>
            <p class="muted mt-4" style="font-size:13.5px">Pilih operator dan masukkan PIN, atau gunakan kata sandi.</p>

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

            <div data-tabs class="mt-16">
                <div class="tabs">
                    <button type="button" class="tab {{ $operators->isNotEmpty() ? 'is-active' : '' }}" data-tab="pin">PIN Cepat</button>
                    <button type="button" class="tab {{ $operators->isEmpty() ? 'is-active' : '' }}" data-tab="sandi">Kata Sandi</button>
                </div>

                {{-- PIN keypad --}}
                <div class="tab-panel {{ $operators->isNotEmpty() ? 'is-active' : '' }}" data-tab-panel="pin">
                    @if ($operators->isEmpty())
                        <div class="alert alert--info">
                            <x-icon name="alert" size="17" class="alert__icon"/>
                            <div>Belum ada operator dengan PIN. Minta Owner mengatur PIN di menu Pengguna, atau gunakan kata sandi.</div>
                        </div>
                    @else
                        <form method="POST" action="{{ route('pos.login.attempt') }}" id="pin-form">
                            @csrf
                            <input type="hidden" name="user_id" id="pin-user">
                            <input type="hidden" name="pin" id="pin-value">

                            <div class="tiny subtle upper mb-8">Pilih operator</div>
                            <div class="operator-strip mb-16">
                                @foreach ($operators as $operator)
                                    <button type="button" class="operator" data-operator="{{ $operator->id }}">
                                        <span class="avatar">{{ $operator->initials() }}</span>
                                        <span class="operator__name">{{ $operator->name }}</span>
                                        <span class="tiny subtle">{{ $operator->role->value }}</span>
                                    </button>
                                @endforeach
                            </div>

                            {{-- PIN panjangnya 4–8 digit, jadi titiknya tumbuh
                                 mengikuti ketikan. Menampilkan jumlah slot
                                 tetap akan menyesatkan operator ber-PIN 4. --}}
                            <div class="pin-display" data-pin-display></div>

                            <div class="t-center tiny subtle mb-12" data-pin-hint>
                                Masukkan PIN Anda (4–8 digit), lalu tekan Masuk
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
                </div>

                {{-- Username + password --}}
                <div class="tab-panel {{ $operators->isEmpty() ? 'is-active' : '' }}" data-tab-panel="sandi">
                    <form method="POST" action="{{ route('pos.login.attempt') }}">
                        @csrf

                        <div class="field">
                            <label class="field__label" for="login">Username atau Email</label>
                            <input type="text" id="login" name="login" value="{{ old('login') }}" class="input"
                                   placeholder="kasir1" autocomplete="username" required>
                        </div>

                        <div class="field">
                            <label class="field__label" for="password">Kata Sandi</label>
                            <input type="password" id="password" name="password" class="input"
                                   placeholder="••••••••" autocomplete="current-password" required>
                        </div>

                        <button type="submit" class="btn btn--primary btn--lg btn--block">
                            <x-icon name="lock" size="16"/> Masuk Kasir
                        </button>
                    </form>
                </div>
            </div>

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

<script src="{{ asset('assets/js/app.js') }}"></script>
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
            // Show the minimum number of slots, growing as the operator types
            // past it — the PIN length is not fixed, and their real PIN is
            // hashed so we cannot know it in advance.
            var slots = Math.max(MIN_PIN, pin.length);
            var dots = '';

            for (var i = 0; i < slots; i++) {
                dots += '<span class="pin-dot' + (i < pin.length ? ' is-filled' : '') + '"></span>';
            }

            display.innerHTML = dots;

            pinField.value = pin;
            userField.value = selected || '';

            var ready = Boolean(selected) && pin.length >= MIN_PIN;
            submit.disabled = !ready;

            if (!selected) {
                hint.textContent = 'Pilih operator terlebih dahulu';
            } else if (pin.length < MIN_PIN) {
                hint.textContent = 'Masukkan PIN Anda (4–8 digit), lalu tekan Masuk';
            } else {
                hint.textContent = 'Tekan Masuk atau Enter untuk melanjutkan';
            }
        }

        document.querySelectorAll('[data-operator]').forEach(function (button) {
            button.addEventListener('click', function () {
                document.querySelectorAll('[data-operator]').forEach(function (b) { b.classList.remove('is-active'); });
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
        // auto-submit: PIN length varies per operator, so the operator
        // decides when the entry is complete.
        document.addEventListener('keydown', function (event) {
            if (!document.querySelector('[data-tab-panel="pin"]').classList.contains('is-active')) return;

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

        // Preselect when there is only one operator to choose from.
        var only = document.querySelectorAll('[data-operator]');
        if (only.length === 1) only[0].click();

        refresh();
    })();
</script>

</body>
</html>
