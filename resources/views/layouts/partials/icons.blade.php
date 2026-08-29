{{--
    Ikon situs, dibuat dari logo Aldef Tech.

    favicon.ico transparan supaya tetap terbaca di bilah tab terang maupun
    gelap; berkas PNG memakai latar gelap karena iOS dan Android menimpa
    transparansi dengan putih - mark berwarna terang akan hilang di sana.
--}}
<link rel="icon" href="{{ asset_v('favicon.ico') }}" sizes="any">
<link rel="icon" type="image/png" sizes="192x192" href="{{ asset_v('assets/img/icon-192.png') }}">
<link rel="apple-touch-icon" href="{{ asset_v('assets/img/apple-touch-icon.png') }}">
<link rel="manifest" href="{{ asset_v('site.webmanifest') }}">
<meta name="theme-color" content="#0d1024">
