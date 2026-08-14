<!DOCTYPE html>
<html lang="id" data-theme="light">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Dashboard') · {{ $tenant?->name ?? config('app.name') }}</title>

    {{-- Applied before first paint so a dark-mode user never sees a flash. --}}
    <script>
        try {
            var t = localStorage.getItem('kasir.theme');
            if (t) document.documentElement.setAttribute('data-theme', t);
        } catch (e) {}
    </script>

    <link rel="stylesheet" href="{{ asset_v('assets/css/app.css') }}">
    @stack('styles')
</head>
<body @if(session('status')) data-flash="{{ session('status') }}" data-flash-type="ok"
      @elseif(session('error')) data-flash="{{ session('error') }}" data-flash-type="bad" @endif>

<div class="sidebar-scrim no-print"></div>

<div class="shell">
    @include('layouts.partials.sidebar')

    <div class="main">
        <header class="topbar no-print">
            <button type="button" class="btn btn--ghost btn--icon" data-sidebar-toggle
                    style="display:none" id="menu-btn">
                <x-icon name="menu"/>
                <span class="sr">Menu</span>
            </button>

            <div class="grow">
                <div class="topbar__title">@yield('title', 'Dashboard')</div>
                @hasSection('subtitle')
                    <div class="topbar__sub">@yield('subtitle')</div>
                @endif
            </div>

            @yield('topbar-actions')

            <a href="{{ route('pos.index') }}" target="_blank" class="btn btn--outline btn--sm">
                <x-icon name="scan" size="15"/>
                <span class="hide-sm">Buka Kasir</span>
            </a>

            <button type="button" class="btn btn--ghost btn--icon" data-theme-toggle title="Ganti tema">
                <x-icon name="sun" class="theme-sun"/>
                <span class="sr" data-theme-label>Mode Gelap</span>
            </button>

            @include('layouts.partials.user-menu')
        </header>

        <main class="content">
            @yield('content')
        </main>
    </div>
</div>

<script src="{{ asset_v('assets/js/app.js') }}"></script>
@stack('scripts')

<style>
    /* The hamburger only exists once the sidebar collapses. */
    @media (max-width: 1024px) { #menu-btn { display: inline-flex !important; } }
    @media (max-width: 720px) { .hide-sm { display: none; } }
</style>
</body>
</html>
