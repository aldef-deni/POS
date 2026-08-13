<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>POS - Kasir</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <style>body{background:#f3f4f6}</style>
</head>
<body>
<div class="p-6">
    <div class="max-w-6xl mx-auto bg-white rounded shadow p-6">
        <div class="flex items-center justify-between mb-6">
            <h2 class="text-xl font-semibold">Kasir POS</h2>
            <div>
                <a href="{{ route('pos.logout') }}" class="text-sm text-gray-600">Logout</a>
            </div>
        </div>

        <div class="grid grid-cols-3 gap-6">
            <div class="col-span-2">
                <div class="grid grid-cols-3 gap-4">
                    @foreach($products as $p)
                        <div class="border rounded p-3 bg-white">
                            <div class="font-medium">{{ $p->name }}</div>
                            <div class="text-sm text-gray-500">SKU: {{ $p->sku }}</div>
                            <div class="mt-2">Rp {{ number_format($p->price,0,',','.') }}</div>
                        </div>
                    @endforeach
                </div>
            </div>
            <div class="col-span-1">
                <div class="bg-gray-50 p-4 rounded">
                    <h3 class="font-semibold mb-3">Transaksi</h3>
                    <p class="text-sm text-gray-500">Keranjang akan muncul di sini (placeholder)</p>
                    <form method="POST" action="{{ route('pos.print') }}" target="_blank">
                        @csrf
                        <input type="hidden" name="dummy" value="1">
                        <button class="mt-4 w-full bg-indigo-600 text-white px-4 py-2 rounded">Cetak Struk</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
</body>
</html>
