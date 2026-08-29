<!DOCTYPE html>
<html lang="id" data-theme="light">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Tutup Shift · {{ $tenant?->name }}</title>

    @include('layouts.partials.icons')
    <link rel="stylesheet" href="{{ asset_v('assets/css/app.css') }}">
    <link rel="stylesheet" href="{{ asset_v('assets/css/pos.css') }}">
</head>
<body>

<div class="pos-auth">
    <div class="pos-auth__card" style="max-width:640px;grid-template-columns:1fr">
        <div class="pos-auth__form">
            <div class="between mb-20">
                <div>
                    <h1 style="font-size:20px">Tutup Shift</h1>
                    <p class="muted" style="font-size:13px">
                        {{ $cashier->name }} · dibuka {{ $shift->opened_at->format('H:i') }} ({{ $shift->durationLabel() }})
                    </p>
                </div>
                <a href="{{ route('pos.index') }}" class="btn btn--ghost btn--sm">
                    <x-icon name="arrow-left" size="15"/> Kembali
                </a>
            </div>

            @if ($errors->any())
                <div class="alert alert--bad">
                    <x-icon name="alert" size="17" class="alert__icon"/>
                    <div>{{ $errors->first() }}</div>
                </div>
            @endif

            {{-- What the system expects to find in the drawer --}}
            <div class="card card--flat mb-16" style="background:var(--bg-sunken)">
                <div class="card__body">
                    <div class="total-row"><span>Modal awal</span><span>{{ money($shift->opening_cash) }}</span></div>
                    <div class="total-row"><span>Penjualan tunai (bersih)</span><span>{{ money($shift->cash_sales) }}</span></div>
                    <div class="total-row"><span>Penjualan non-tunai</span><span>{{ money($shift->non_cash_sales) }}</span></div>
                    <div class="total-row"><span>Jumlah transaksi</span><span>{{ $salesCount }}</span></div>
                    <div class="total-row total-row--grand">
                        <span>Kas seharusnya</span>
                        <span data-expected="{{ $shift->expectedCashNow() }}">{{ money($shift->expectedCashNow()) }}</span>
                    </div>
                </div>
            </div>

            <form method="POST" action="{{ route('pos.shift.close.store') }}">
                @csrf

                <div class="field">
                    <label class="field__label" for="counted_cash">
                        Hitungan Fisik Uang di Laci <span class="field__req">*</span>
                    </label>
                    <div class="input-group">
                        <span class="input-group__addon">{{ $tenant?->currency_symbol ?? 'Rp' }}</span>
                        <input type="number" step="0.01" min="0" id="counted_cash" name="counted_cash"
                               class="input" style="font-size:21px;font-weight:680;text-align:right"
                               required autofocus placeholder="0">
                    </div>
                    <span class="field__hint">Hitung seluruh uang tunai di laci, termasuk modal awal.</span>
                </div>

                {{-- Live variance, so a shortfall is obvious before submitting --}}
                <div class="change-box mb-16" id="variance-box" style="display:none">
                    <div>
                        <div class="change-box__label" id="variance-label">Selisih Kas</div>
                        <div class="tiny muted mt-4" id="variance-hint"></div>
                    </div>
                    <div class="change-box__value" id="variance-value">—</div>
                </div>

                <div class="field">
                    <label class="field__label" for="closing_note">Catatan Penutupan</label>
                    <textarea id="closing_note" name="closing_note" class="textarea"
                              placeholder="Jelaskan bila terdapat selisih kas"></textarea>
                </div>

                <button type="submit" class="btn btn--primary btn--xl btn--block"
                        data-confirm="Tutup shift sekarang? Anda tidak dapat melanjutkan transaksi pada shift ini.">
                    <x-icon name="check" size="18"/> Tutup Shift
                </button>
            </form>
        </div>
    </div>
</div>

<script src="{{ asset_v('assets/js/app.js') }}"></script>
<script>
    (function () {
        var expected = parseFloat(document.querySelector('[data-expected]').getAttribute('data-expected'));
        var input = document.getElementById('counted_cash');
        var box = document.getElementById('variance-box');
        var label = document.getElementById('variance-label');
        var value = document.getElementById('variance-value');
        var hint = document.getElementById('variance-hint');

        input.addEventListener('input', function () {
            if (input.value === '') { box.style.display = 'none'; return; }

            var diff = parseFloat(input.value) - expected;
            box.style.display = 'flex';

            if (Math.abs(diff) < 0.01) {
                box.classList.remove('change-box--short');
                label.textContent = 'Kas Sesuai';
                hint.textContent = 'Hitungan cocok dengan sistem.';
                value.textContent = window.posFormat.money(0, '{{ $tenant?->currency_symbol ?? "Rp" }}');
            } else if (diff > 0) {
                box.classList.remove('change-box--short');
                label.textContent = 'Kas Lebih';
                hint.textContent = 'Uang di laci melebihi catatan sistem.';
                value.textContent = '+' + window.posFormat.money(diff, '{{ $tenant?->currency_symbol ?? "Rp" }}');
            } else {
                box.classList.add('change-box--short');
                label.textContent = 'Kas Kurang';
                hint.textContent = 'Mohon jelaskan penyebabnya pada catatan.';
                value.textContent = '−' + window.posFormat.money(Math.abs(diff), '{{ $tenant?->currency_symbol ?? "Rp" }}');
            }
        });
    })();
</script>

</body>
</html>
