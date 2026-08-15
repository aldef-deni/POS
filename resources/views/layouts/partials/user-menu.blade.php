@php $me = auth('web')->user(); @endphp

<div class="dropdown">
    <button type="button" class="btn btn--ghost" data-dropdown style="padding:5px 9px 5px 5px;gap:9px">
        <span class="avatar avatar--sm">
            @if ($me?->avatarUrl())
                <img src="{{ $me->avatarUrl() }}" alt="">
            @else
                {{ $me?->initials() }}
            @endif
        </span>
        <span class="stack" style="align-items:flex-start;line-height:1.25">
            <span style="font-size:12.5px;font-weight:620">{{ $me?->name }}</span>
            <span class="tiny subtle">{{ $me?->role?->label() }}</span>
        </span>
        <x-icon name="chevron-down" size="14" class="subtle"/>
    </button>

    <div class="dropdown__menu">
        <div class="dropdown__label">Masuk sebagai</div>
        <div style="padding:0 10px 8px">
            <div class="semi small">{{ $me?->email }}</div>
            <div class="tiny subtle">{{ $me?->role?->description() }}</div>
        </div>

        <div class="dropdown__sep"></div>

        <a href="{{ route('admin.profile') }}" class="dropdown__item">
            <x-icon name="user" size="16"/>
            Profil Saya
        </a>

        <button type="button" class="dropdown__item" data-theme-toggle>
            <x-icon name="moon" size="16"/>
            <span data-theme-label>Mode Gelap</span>
        </button>

        <a href="{{ route('pos.index') }}" target="_blank" class="dropdown__item">
            <x-icon name="scan" size="16"/>
            Terminal Kasir
        </a>

        <div class="dropdown__sep"></div>

        <form method="POST" action="{{ route('admin.logout') }}">
            @csrf
            <button type="submit" class="dropdown__item dropdown__item--danger">
                <x-icon name="logout" size="16"/>
                Keluar
            </button>
        </form>
    </div>
</div>
