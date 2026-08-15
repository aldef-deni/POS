@php
    /**
     * The profile body, shared by the dashboard and the terminal.
     *
     * $rp is the route-name prefix of whichever door the person came in
     * through ("admin.profile" or "pos.profile"), so the same markup posts
     * back to the right guard.
     */
    $avatar = $user->avatarUrl();
@endphp

@if (session('status'))
    <div class="alert alert--ok">
        <x-icon name="check-circle" size="17" class="alert__icon"/>
        <div>{{ session('status') }}</div>
    </div>
@endif

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

<div class="grid grid-1-2">

    {{-- ======================= Identity card ======================= --}}
    <div class="stack g-16">
        <div class="card">
            <div class="card__body t-center">
                <div class="profile-photo">
                    @if ($avatar)
                        <img src="{{ $avatar }}" alt="{{ $user->name }}">
                    @else
                        <span class="profile-photo__initials">{{ $user->initials() }}</span>
                    @endif
                </div>

                <h2 class="mt-12">{{ $user->name }}</h2>

                <div class="row g-6 mt-8" style="justify-content:center;flex-wrap:wrap">
                    <span class="badge badge--{{ $user->role->badgeColor() }}">{{ $user->role->label() }}</span>

                    @if ($user->outlet)
                        <span class="badge badge--neutral">
                            <x-icon name="store" size="11"/> {{ $user->outlet->name }}
                        </span>
                    @else
                        <span class="badge badge--violet">
                            <x-icon name="layers" size="11"/> Semua Outlet
                        </span>
                    @endif

                    <span class="badge badge--{{ $user->is_active ? 'ok' : 'bad' }}">
                        {{ $user->is_active ? 'Aktif' : 'Nonaktif' }}
                    </span>
                </div>

                <p class="tiny subtle mt-12">{{ $user->role->description() }}</p>

                <div class="divider"></div>

                {{-- Upload keeps its own form: a file field cannot ride along
                     with the ordinary details form without complicating it. --}}
                <form method="POST" action="{{ route($rp.'.avatar') }}" enctype="multipart/form-data">
                    @csrf
                    <input type="file" name="avatar" accept="image/jpeg,image/png,image/webp"
                           class="input" id="avatar-input" data-file-name="#avatar-name"
                           style="display:none" onchange="this.form.submit()">

                    <label for="avatar-input" class="btn btn--outline btn--block" style="cursor:pointer">
                        <x-icon name="user" size="15"/>
                        {{ $avatar ? 'Ganti Foto' : 'Unggah Foto' }}
                    </label>
                </form>

                @if ($avatar)
                    <form method="POST" action="{{ route($rp.'.avatar.destroy') }}" class="mt-8">
                        @csrf @method('DELETE')
                        <button type="submit" class="btn btn--ghost btn--sm bad btn--block"
                                data-confirm="Hapus foto profil?">
                            <x-icon name="trash" size="14"/> Hapus Foto
                        </button>
                    </form>
                @endif

                <p class="tiny subtle mt-8">
                    JPG, PNG, atau WEBP · maksimal 4 MB.<br>
                    Gambar dipotong persegi otomatis.
                </p>
            </div>
        </div>

        {{-- Read-only facts --}}
        <div class="card">
            <div class="card__head"><div class="card__title">Informasi Akun</div></div>
            <div class="card__body">
                <div class="row between small">
                    <span class="muted">Peran</span>
                    <span class="semi">{{ $user->role->label() }}</span>
                </div>
                <div class="row between small mt-8">
                    <span class="muted">Outlet</span>
                    <span class="semi">{{ $user->outlet?->name ?? 'Semua Outlet' }}</span>
                </div>
                <div class="row between small mt-8">
                    <span class="muted">PIN kasir</span>
                    <span class="semi">{{ $user->pos_pin ? 'Sudah diatur' : 'Belum diatur' }}</span>
                </div>
                <div class="row between small mt-8">
                    <span class="muted">Bergabung</span>
                    <span class="semi">{{ $user->created_at?->translatedFormat('d M Y') ?? '—' }}</span>
                </div>
                <div class="row between small mt-8">
                    <span class="muted">Masuk dashboard</span>
                    <span class="semi">{{ $user->last_login_at?->translatedFormat('d M Y H:i') ?? '—' }}</span>
                </div>
                <div class="row between small mt-8">
                    <span class="muted">Masuk kasir</span>
                    <span class="semi">{{ $user->last_pos_login_at?->translatedFormat('d M Y H:i') ?? '—' }}</span>
                </div>

                <div class="alert alert--info mt-16" style="margin-bottom:0">
                    <x-icon name="shield" size="16" class="alert__icon"/>
                    <div class="tiny">
                        Peran dan outlet hanya dapat diubah oleh Owner — itulah yang menjaga
                        batas akses tetap utuh.
                    </div>
                </div>
            </div>
        </div>

        {{-- Their own numbers --}}
        <div class="card">
            <div class="card__head"><div class="card__title">Aktivitas Saya</div></div>
            <div class="card__body">
                <div class="row between small">
                    <span class="muted">Transaksi hari ini</span>
                    <span class="semi">{{ $stats['today_count'] }} · {{ money($stats['today_total']) }}</span>
                </div>
                <div class="row between small mt-8">
                    <span class="muted">Total transaksi</span>
                    <span class="semi">{{ number_format($stats['sales_count'], 0, ',', '.') }}</span>
                </div>
                <div class="row between small mt-8">
                    <span class="muted">Total nilai penjualan</span>
                    <span class="semi">{{ money($stats['sales_total']) }}</span>
                </div>
                <div class="row between small mt-8">
                    <span class="muted">Shift dijalankan</span>
                    <span class="semi">{{ $stats['shifts_count'] }}</span>
                </div>
                <div class="row between small mt-8">
                    <span class="muted">Shift berjalan</span>
                    @if ($stats['open_shift'])
                        <span class="semi ok">
                            Sejak {{ $stats['open_shift']->opened_at->format('H:i') }}
                        </span>
                    @else
                        <span class="subtle">Tidak ada</span>
                    @endif
                </div>
            </div>
        </div>
    </div>

    {{-- ======================= Editable forms ======================= --}}
    <div class="stack g-16">
        <div class="card">
            <div class="card__head">
                <div>
                    <div class="card__title">Data Diri</div>
                    <div class="card__sub">Nama yang tampil pada struk dan laporan</div>
                </div>
            </div>

            <form method="POST" action="{{ route($rp.'.update') }}">
                @csrf @method('PUT')

                <div class="card__body">
                    <div class="field">
                        <label class="field__label">Nama Lengkap <span class="field__req">*</span></label>
                        <input type="text" name="name" class="input @error('name') is-error @enderror"
                               value="{{ old('name', $user->name) }}" required>
                        @error('name') <span class="field__error">{{ $message }}</span> @enderror
                    </div>

                    <div class="grid grid-2">
                        <div class="field">
                            <label class="field__label">Username <span class="field__req">*</span></label>
                            <input type="text" name="username" class="input mono @error('username') is-error @enderror"
                                   value="{{ old('username', $user->username) }}" required>
                            @error('username') <span class="field__error">{{ $message }}</span> @enderror
                        </div>

                        <div class="field">
                            <label class="field__label">Telepon</label>
                            <input type="text" name="phone" class="input"
                                   value="{{ old('phone', $user->phone) }}" placeholder="0812…">
                        </div>
                    </div>

                    <div class="field" style="margin-bottom:0">
                        <label class="field__label">Email <span class="field__req">*</span></label>
                        <input type="email" name="email" class="input @error('email') is-error @enderror"
                               value="{{ old('email', $user->email) }}" required>
                        @error('email') <span class="field__error">{{ $message }}</span> @enderror
                    </div>
                </div>

                <div class="card__foot t-right">
                    <button type="submit" class="btn btn--primary">
                        <x-icon name="check" size="16"/> Simpan Data Diri
                    </button>
                </div>
            </form>
        </div>

        <div class="card">
            <div class="card__head">
                <div>
                    <div class="card__title">Ganti Kata Sandi</div>
                    <div class="card__sub">Dipakai untuk masuk dashboard</div>
                </div>
            </div>

            <form method="POST" action="{{ route($rp.'.password') }}">
                @csrf @method('PUT')

                <div class="card__body">
                    <div class="field">
                        <label class="field__label">Kata Sandi Saat Ini <span class="field__req">*</span></label>
                        <input type="password" name="current_password" autocomplete="current-password"
                               class="input @error('current_password') is-error @enderror" required>
                        @error('current_password') <span class="field__error">{{ $message }}</span> @enderror
                    </div>

                    <div class="grid grid-2" style="margin-bottom:0">
                        <div class="field" style="margin-bottom:0">
                            <label class="field__label">Kata Sandi Baru <span class="field__req">*</span></label>
                            <input type="password" name="password" autocomplete="new-password"
                                   class="input @error('password') is-error @enderror" required>
                            @error('password') <span class="field__error">{{ $message }}</span> @enderror
                        </div>
                        <div class="field" style="margin-bottom:0">
                            <label class="field__label">Ulangi Kata Sandi <span class="field__req">*</span></label>
                            <input type="password" name="password_confirmation" autocomplete="new-password"
                                   class="input" required>
                        </div>
                    </div>
                </div>

                <div class="card__foot t-right">
                    <button type="submit" class="btn btn--outline">
                        <x-icon name="lock" size="16"/> Ganti Kata Sandi
                    </button>
                </div>
            </form>
        </div>

        <div class="card">
            <div class="card__head">
                <div>
                    <div class="card__title">Ganti PIN Kasir</div>
                    <div class="card__sub">
                        Dipakai untuk masuk terminal kasir
                        @if ($user->role->canApproveVoid()) dan menyetujui pembatalan transaksi @endif
                    </div>
                </div>
            </div>

            <form method="POST" action="{{ route($rp.'.pin') }}">
                @csrf @method('PUT')

                <div class="card__body">
                    @if ($user->pos_pin)
                        <div class="field">
                            <label class="field__label">PIN Saat Ini <span class="field__req">*</span></label>
                            <input type="password" name="current_pin" inputmode="numeric" maxlength="8"
                                   class="input mono @error('current_pin') is-error @enderror" required>
                            @error('current_pin') <span class="field__error">{{ $message }}</span> @enderror
                        </div>
                    @else
                        <div class="alert alert--warn">
                            <x-icon name="alert" size="16" class="alert__icon"/>
                            <div class="tiny">
                                Anda belum memiliki PIN. Tanpa PIN, akun ini tidak dapat masuk ke terminal kasir.
                            </div>
                        </div>
                    @endif

                    <div class="grid grid-2" style="margin-bottom:0">
                        <div class="field" style="margin-bottom:0">
                            <label class="field__label">PIN Baru <span class="field__req">*</span></label>
                            <input type="password" name="pos_pin" inputmode="numeric" maxlength="8"
                                   class="input mono @error('pos_pin') is-error @enderror"
                                   placeholder="4–8 digit" required>
                            @error('pos_pin') <span class="field__error">{{ $message }}</span> @enderror
                        </div>
                        <div class="field" style="margin-bottom:0">
                            <label class="field__label">Ulangi PIN <span class="field__req">*</span></label>
                            <input type="password" name="pos_pin_confirmation" inputmode="numeric" maxlength="8"
                                   class="input mono" required>
                        </div>
                    </div>
                </div>

                <div class="card__foot t-right">
                    <button type="submit" class="btn btn--outline">
                        <x-icon name="scan" size="16"/> Ganti PIN
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
