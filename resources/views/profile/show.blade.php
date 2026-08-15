@extends('layouts.app')

@section('title', 'Profil Saya')
@section('subtitle', 'Kelola data diri, foto, kata sandi, dan PIN Anda')

@section('content')

<div class="page-head">
    <div>
        <h1>Profil Saya</h1>
        <p class="muted mt-4">Perubahan di sini hanya berlaku untuk akun Anda sendiri.</p>
    </div>
</div>

@include('profile._content', ['rp' => 'admin.profile'])

@endsection
