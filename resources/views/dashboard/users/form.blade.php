@extends('layouts.app')

@section('title', $user->exists ? 'Ubah Pengguna' : 'Pengguna Baru')

@section('content')

<form method="POST" action="{{ $user->exists ? route('admin.users.update', $user) : route('admin.users.store') }}">
    @csrf
    @if ($user->exists) @method('PUT') @endif

    <div class="page-head">
        <div class="row g-12">
            <a href="{{ route('admin.users.index') }}" class="btn btn--ghost btn--icon"><x-icon name="arrow-left" size="18"/></a>
            <div>
                <h1>{{ $user->exists ? 'Ubah Pengguna' : 'Pengguna Baru' }}</h1>
                <p class="muted mt-4">{{ $user->exists ? $user->name : 'Tentukan peran untuk membatasi aksesnya' }}</p>
            </div>
        </div>
        <button type="submit" class="btn btn--primary"><x-icon name="check" size="16"/> Simpan</button>
    </div>

    @if ($errors->any())
        <div class="alert alert--bad">
            <x-icon name="alert" size="17" class="alert__icon"/>
            <div>
                <div class="semi">Periksa kembali isian berikut:</div>
                <ul style="margin:6px 0 0;padding-left:18px">
                    @foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach
                </ul>
            </div>
        </div>
    @endif

    <div class="grid grid-2-1">
        <div class="stack g-16">
            <div class="card">
                <div class="card__head"><div class="card__title">Identitas</div></div>
                <div class="card__body">
                    <div class="field">
                        <label class="field__label">Nama Lengkap <span class="field__req">*</span></label>
                        <input type="text" name="name" class="input" value="{{ old('name', $user->name) }}" required autofocus>
                    </div>

                    <div class="grid grid-2">
                        <div class="field">
                            <label class="field__label">Username <span class="field__req">*</span></label>
                            <input type="text" name="username" class="input mono"
                                   value="{{ old('username', $user->username) }}" required
                                   placeholder="kasir1">
                            <span class="field__hint">Dipakai untuk masuk di terminal kasir.</span>
                        </div>
                        <div class="field">
                            <label class="field__label">Email <span class="field__req">*</span></label>
                            <input type="email" name="email" class="input" value="{{ old('email', $user->email) }}" required>
                        </div>
                    </div>

                    <div class="field" style="margin-bottom:0">
                        <label class="field__label">Telepon</label>
                        <input type="text" name="phone" class="input" value="{{ old('phone', $user->phone) }}">
                    </div>
                </div>
            </div>

            <div class="card">
                <div class="card__head">
                    <div>
                        <div class="card__title">Kredensial</div>
                        <div class="card__sub">
                            {{ $user->exists ? 'Kosongkan bila tidak ingin mengubah' : 'Kata sandi minimal 8 karakter' }}
                        </div>
                    </div>
                </div>
                <div class="card__body">
                    <div class="grid grid-2">
                        <div class="field">
                            <label class="field__label">
                                Kata Sandi @unless ($user->exists) <span class="field__req">*</span> @endunless
                            </label>
                            <input type="password" name="password" class="input" autocomplete="new-password"
                                   @unless ($user->exists) required @endunless>
                        </div>
                        <div class="field">
                            <label class="field__label">
                                Ulangi Kata Sandi @unless ($user->exists) <span class="field__req">*</span> @endunless
                            </label>
                            <input type="password" name="password_confirmation" class="input" autocomplete="new-password"
                                   @unless ($user->exists) required @endunless>
                        </div>
                    </div>

                    <div class="field" style="margin-bottom:0">
                        <label class="field__label">PIN Terminal Kasir</label>
                        <input type="text" name="pos_pin" class="input mono" maxlength="8" inputmode="numeric"
                               placeholder="{{ $user->pos_pin ? 'PIN sudah diatur — isi untuk mengganti' : '4–8 digit angka' }}">
                        <span class="field__hint">
                            PIN dipakai untuk masuk cepat di kasir dan menyetujui pembatalan transaksi.
                        </span>
                    </div>
                </div>
            </div>
        </div>

        <div class="stack g-16">
            {{-- Placed above the role card on purpose: putting an operator in
                 the wrong branch is the costliest mistake on this form, so it
                 is asked first and never pre-answered. --}}
            <div class="card" style="border-color:var(--brand-200)">
                <div class="card__head">
                    <div>
                        <div class="card__title">Penempatan Outlet <span class="field__req">*</span></div>
                        <div class="card__sub">Menentukan stok, shift, dan laporan yang diakses</div>
                    </div>
                </div>
                <div class="card__body">
                    @php
                        // No default selection — the operator must choose.
                        $currentOutlet = old('outlet_id', $user->exists
                            ? ($user->outlet_id ?? ($user->isOwner() ? 'all' : ''))
                            : '');
                    @endphp

                    <div class="field" style="margin-bottom:12px">
                        <select name="outlet_id" class="select @error('outlet_id') is-error @enderror" required
                                data-outlet-select>
                            <option value="" @selected($currentOutlet === '')>— Pilih outlet penempatan —</option>

                            @foreach ($outlets as $option)
                                <option value="{{ $option->id }}" @selected((string) $currentOutlet === (string) $option->id)>
                                    {{ $option->name }} ({{ $option->code }}){{ $option->city ? ' · '.$option->city : '' }}
                                </option>
                            @endforeach

                            <option value="all" @selected($currentOutlet === 'all')>
                                Semua Outlet — khusus Owner
                            </option>
                        </select>

                        @error('outlet_id')
                            <span class="field__error">{{ $message }}</span>
                        @enderror
                    </div>

                    <div class="alert alert--warn" style="margin:0" data-outlet-warning hidden>
                        <x-icon name="alert" size="16" class="alert__icon"/>
                        <div class="tiny">
                            Pilihan <b>Semua Outlet</b> hanya berlaku untuk Owner. Supervisor dan Kasir
                            wajib ditempatkan pada satu outlet agar transaksi dan stok tidak tertukar.
                        </div>
                    </div>

                    <p class="field__hint mt-8">
                        Kasir hanya dapat menjual stok outlet ini, dan seluruh transaksinya tercatat di sini.
                    </p>
                </div>
            </div>

            <div class="card">
                <div class="card__head"><div class="card__title">Peran &amp; Akses</div></div>
                <div class="card__body">
                    @php $editingSelf = $user->exists && $user->id === auth('web')->id(); @endphp

                    @if ($editingSelf)
                        {{-- The radios below are disabled so nobody demotes
                             themselves, but a disabled input submits nothing —
                             without this the form would fail validation and no
                             edit to your own account could ever be saved. --}}
                        <input type="hidden" name="role" value="{{ $user->role->value }}">
                    @endif

                    @foreach ($roles as $role)
                        <label class="check mb-16" style="align-items:flex-start">
                            <input type="radio" name="role" value="{{ $role->value }}" data-role-radio
                                   @checked(old('role', $user->role?->value ?? 'Kasir') === $role->value)
                                   @disabled($editingSelf)>
                            <span>
                                <span class="check__text semi">{{ $role->label() }}</span>
                                <span class="check__hint" style="display:block;margin-top:2px">{{ $role->description() }}</span>
                            </span>
                        </label>
                    @endforeach

                    @if ($editingSelf)
                        <div class="alert alert--info" style="margin:0">
                            <x-icon name="shield" size="16" class="alert__icon"/>
                            <div>Anda tidak dapat mengubah peran akun sendiri.</div>
                        </div>
                    @endif
                </div>
            </div>

            <div class="card">
                <div class="card__head"><div class="card__title">Status</div></div>
                <div class="card__body">
                    <label class="switch">
                        <input type="checkbox" name="is_active" value="1"
                               @checked(old('is_active', $user->exists ? $user->is_active : true))
                               @disabled($editingSelf)>
                        <span class="switch__track"></span>
                        <span class="check__text">Akun aktif</span>
                    </label>
                    <p class="field__hint mt-8">
                        Akun nonaktif tidak dapat masuk ke dashboard maupun terminal kasir.
                    </p>
                </div>
            </div>
        </div>
    </div>
</form>

@push('scripts')
<script>
    // Catches the mismatch in the browser so the operator sees it while
    // choosing; the server enforces the same rule regardless.
    (function () {
        var select = document.querySelector('[data-outlet-select]');
        var warning = document.querySelector('[data-outlet-warning]');
        if (!select || !warning) return;

        function currentRole() {
            var checked = document.querySelector('[data-role-radio]:checked');
            return checked ? checked.value : 'Kasir';
        }

        function sync() {
            var mismatch = select.value === 'all' && currentRole() !== 'Owner';
            warning.hidden = !mismatch;
            select.classList.toggle('is-error', mismatch);
        }

        select.addEventListener('change', sync);
        document.querySelectorAll('[data-role-radio]').forEach(function (radio) {
            radio.addEventListener('change', sync);
        });

        sync();
    })();
</script>
@endpush

@endsection
