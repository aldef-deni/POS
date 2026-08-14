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
            <div class="card">
                <div class="card__head"><div class="card__title">Peran &amp; Akses</div></div>
                <div class="card__body">
                    @foreach ($roles as $role)
                        <label class="check mb-16" style="align-items:flex-start">
                            <input type="radio" name="role" value="{{ $role->value }}"
                                   @checked(old('role', $user->role?->value ?? 'Kasir') === $role->value)
                                   @disabled($user->exists && $user->id === auth('web')->id())>
                            <span>
                                <span class="check__text semi">{{ $role->label() }}</span>
                                <span class="check__hint" style="display:block;margin-top:2px">{{ $role->description() }}</span>
                            </span>
                        </label>
                    @endforeach

                    @if ($user->exists && $user->id === auth('web')->id())
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
                               @disabled($user->exists && $user->id === auth('web')->id())>
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

@endsection
