<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>POS Login</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <style>body{background:#f8fafc}</style>
</head>
<body>
<div class="min-h-screen flex items-center justify-center">
    <div class="w-full max-w-md bg-white shadow-md rounded-lg p-6">
        <h1 class="text-2xl font-semibold mb-4">Operator POS Login</h1>
        @if($errors->any())
            <div class="bg-red-100 text-red-700 p-2 rounded mb-3">{{ $errors->first() }}</div>
        @endif
        <form method="POST" action="{{ route('pos.login.post') }}">
            @csrf
            <label class="block mb-2">Email</label>
            <input type="email" name="email" required class="w-full border px-3 py-2 rounded mb-3" />
            <label class="block mb-2">Password</label>
            <input type="password" name="password" required class="w-full border px-3 py-2 rounded mb-4" />
            <button class="w-full bg-indigo-600 text-white px-4 py-2 rounded">Login to POS</button>
        </form>
    </div>
</div>
</body>
</html>
