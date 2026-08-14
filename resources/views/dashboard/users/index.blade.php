@extends('layouts.app')

@section('title', 'Pengguna & Peran')
@section('subtitle', 'Kelola operator beserta batas aksesnya')

@section('content')

<div class="page-head">
    <div>
        <h1>Pengguna &amp; Peran</h1>
        <p class="muted mt-4">{{ $users->count() }} operator terdaftar pada {{ $tenant?->name }}.</p>
    </div>
    <a href="{{ route('admin.users.create') }}" class="btn btn--primary">
        <x-icon name="plus" size="16"/> Pengguna Baru
    </a>
</div>

{{-- Role reference, so an Owner can see exactly what each role grants. --}}
<div class="grid grid-3 mb-20">
    @foreach ($roles as $role)
        <div class="card card--pad">
            <div class="row g-10 mb-8">
                <span class="badge badge--{{ $role->badgeColor() }}">{{ $role->label() }}</span>
                <span class="tiny subtle">{{ count($role->permissions()) }} izin</span>
            </div>
            <p class="small muted">{{ $role->description() }}</p>

            <div class="divider"></div>

            <div class="row g-6 wrap">
                @if ($role->canAccessDashboard())
                    <span class="badge badge--ok"><x-icon name="check" size="11"/> Dashboard</span>
                @else
                    <span class="badge badge--bad"><x-icon name="x" size="11"/> Dashboard</span>
                @endif

                <span class="badge badge--ok"><x-icon name="check" size="11"/> Kasir</span>

                @if ($role->can('report.profit'))
                    <span class="badge badge--ok"><x-icon name="check" size="11"/> Lihat laba</span>
                @endif
                @if ($role->can('user.manage'))
                    <span class="badge badge--violet"><x-icon name="shield" size="11"/> Kelola pengguna</span>
                @endif
                @if ($role->can('settings.manage'))
                    <span class="badge badge--violet"><x-icon name="settings" size="11"/> Pengaturan</span>
                @endif
            </div>
        </div>
    @endforeach
</div>

<div class="card">
    <div class="table-wrap">
        <table class="table">
            <thead>
                <tr>
                    <th>Nama</th><th>Username</th><th>Outlet</th><th>Peran</th>
                    <th>PIN Kasir</th><th>Status</th><th>Terakhir Masuk</th><th></th>
                </tr>
            </thead>
            <tbody>
                @foreach ($users as $user)
                    <tr>
                        <td>
                            <div class="row g-10">
                                <span class="avatar avatar--sm">{{ $user->initials() }}</span>
                                <div>
                                    <div class="semi">{{ $user->name }}</div>
                                    @if ($user->id === auth('web')->id())
                                        <div class="tiny subtle">Akun Anda</div>
                                    @elseif ($user->phone)
                                        <div class="tiny subtle">{{ $user->phone }}</div>
                                    @endif
                                </div>
                            </div>
                        </td>
                        <td>
                            <span class="code-chip">{{ $user->username }}</span>
                            <div class="tiny subtle mt-4">{{ $user->email }}</div>
                        </td>
                        <td>
                            @if ($user->outlet)
                                <span class="badge badge--neutral">
                                    <x-icon name="store" size="11"/> {{ $user->outlet->name }}
                                </span>
                            @elseif ($user->isOwner())
                                <span class="badge badge--violet">
                                    <x-icon name="layers" size="11"/> Semua Outlet
                                </span>
                            @else
                                {{-- A non-Owner with no branch cannot work; make it loud. --}}
                                <span class="badge badge--bad">
                                    <x-icon name="alert" size="11"/> Belum ditempatkan
                                </span>
                            @endif
                        </td>
                        <td><span class="badge badge--{{ $user->role->badgeColor() }}">{{ $user->role->label() }}</span></td>
                        <td>
                            @if ($user->pos_pin)
                                <span class="badge badge--ok"><x-icon name="check" size="11"/> Aktif</span>
                            @else
                                <span class="badge badge--neutral">Belum diatur</span>
                            @endif
                        </td>
                        <td>
                            <span class="badge badge--{{ $user->is_active ? 'ok' : 'bad' }}">
                                {{ $user->is_active ? 'Aktif' : 'Nonaktif' }}
                            </span>
                        </td>
                        <td class="small muted nowrap">
                            {{ $user->last_login_at?->format('d/m/y H:i') ?? $user->last_pos_login_at?->format('d/m/y H:i') ?? '—' }}
                        </td>
                        <td class="t-right">
                            <div class="row g-6" style="justify-content:flex-end">
                                <a href="{{ route('admin.users.edit', $user) }}" class="btn btn--ghost btn--sm">
                                    <x-icon name="edit" size="15"/>
                                </a>
                                @if ($user->id !== auth('web')->id())
                                    <form method="POST" action="{{ route('admin.users.destroy', $user) }}">
                                        @csrf @method('DELETE')
                                        <button type="submit" class="btn btn--ghost btn--sm bad"
                                                data-confirm="Hapus atau nonaktifkan {{ $user->name }}?">
                                            <x-icon name="trash" size="15"/>
                                        </button>
                                    </form>
                                @endif
                            </div>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>

@endsection
