<!DOCTYPE html>
<html lang="id" data-theme="light">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Profil Saya · Kasir</title>

    <script>
        try {
            var t = localStorage.getItem('kasir.theme');
            if (t) document.documentElement.setAttribute('data-theme', t);
        } catch (e) {}
    </script>

    <link rel="stylesheet" href="{{ asset_v('assets/css/app.css') }}">
    <link rel="stylesheet" href="{{ asset_v('assets/css/pos.css') }}">
</head>
<body>

<header class="pos-top">
    <a href="{{ route('pos.index') }}" class="btn btn--ghost btn--icon"><x-icon name="arrow-left" size="18"/></a>

    <div class="grow">
        <div class="pos-top__name">Profil Saya</div>
        <div class="pos-top__meta">
            {{ $user->name }} · {{ $user->role->label() }}
            @if ($user->outlet) · {{ $user->outlet->name }} @endif
        </div>
    </div>

    <button type="button" class="btn btn--ghost btn--icon" data-theme-toggle title="Ganti tema">
        <x-icon name="sun"/>
    </button>

    <a href="{{ route('pos.index') }}" class="btn btn--primary btn--sm">
        <x-icon name="scan" size="15"/> Kembali ke Kasir
    </a>
</header>

<div class="content" style="max-width:1100px">
    @include('profile._content', ['rp' => 'pos.profile'])
</div>

<script src="{{ asset_v('assets/js/app.js') }}"></script>
</body>
</html>
