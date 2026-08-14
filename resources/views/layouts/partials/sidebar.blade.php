@php
    /**
     * Navigation is filtered by permission, so a Supervisor simply never sees
     * the sections they cannot open rather than meeting a 403.
     */
    $groups = [
        'Ikhtisar' => [
            ['route' => 'admin.dashboard', 'label' => 'Dashboard', 'icon' => 'gauge', 'can' => null],
            ['route' => 'admin.reports.index', 'label' => 'Laporan', 'icon' => 'chart', 'can' => 'report.view'],
        ],
        'Katalog' => [
            ['route' => 'admin.products.index', 'label' => 'Produk', 'icon' => 'package', 'can' => 'product.view'],
            ['route' => 'admin.categories.index', 'label' => 'Kategori', 'icon' => 'tags', 'can' => 'category.manage'],
            ['route' => 'admin.stock.index', 'label' => 'Stok & Inventori', 'icon' => 'boxes', 'can' => 'stock.view'],
        ],
        'Transaksi' => [
            ['route' => 'admin.sales.index', 'label' => 'Penjualan', 'icon' => 'receipt', 'can' => 'sale.view'],
            ['route' => 'admin.shifts.index', 'label' => 'Shift Kasir', 'icon' => 'clock', 'can' => 'shift.view.all'],
            ['route' => 'admin.customers.index', 'label' => 'Pelanggan', 'icon' => 'users', 'can' => 'customer.manage'],
        ],
        'Administrasi' => [
            ['route' => 'admin.users.index', 'label' => 'Pengguna & Peran', 'icon' => 'shield', 'can' => 'user.manage'],
            ['route' => 'admin.settings.index', 'label' => 'Pengaturan', 'icon' => 'settings', 'can' => 'settings.manage'],
            ['route' => 'admin.activity.index', 'label' => 'Log Aktivitas', 'icon' => 'activity', 'can' => 'audit.view'],
        ],
    ];

    $lowStockCount = \App\Models\Product::lowStock()->active()->count();
@endphp

<aside class="sidebar no-print">
    <div class="sidebar__brand">
        <div class="brand-mark">{{ mb_substr($tenant?->name ?? 'KP', 0, 2) }}</div>
        <div class="grow" style="min-width:0">
            <div class="brand-name truncate">{{ $tenant?->name ?? 'Kasir POS' }}</div>
            <div class="brand-sub">{{ ucfirst($tenant?->plan ?? 'pro') }} · {{ $tenant?->business_type ?? 'Retail' }}</div>
        </div>
    </div>

    <nav class="sidebar__nav">
        @foreach ($groups as $groupLabel => $items)
            @php
                $visible = array_filter(
                    $items,
                    fn ($item) => $item['can'] === null || auth('web')->user()?->hasPermission($item['can'])
                );
            @endphp

            @if (count($visible))
                <div class="nav-group">
                    <div class="nav-group__label">{{ $groupLabel }}</div>

                    @foreach ($visible as $item)
                        @php $active = request()->routeIs(str_replace('.index', '.*', $item['route'])); @endphp

                        <a href="{{ route($item['route']) }}" class="nav-item {{ $active ? 'is-active' : '' }}">
                            <x-icon :name="$item['icon']" class="nav-item__icon"/>
                            <span class="grow truncate">{{ $item['label'] }}</span>

                            @if ($item['route'] === 'admin.stock.index' && $lowStockCount > 0)
                                <span class="nav-item__badge">{{ $lowStockCount }}</span>
                            @endif
                        </a>
                    @endforeach
                </div>
            @endif
        @endforeach
    </nav>

    <div class="sidebar__foot">
        <a href="{{ route('pos.index') }}" target="_blank" class="btn btn--soft btn--block btn--sm">
            <x-icon name="scan" size="15"/>
            Terminal Kasir
        </a>
    </div>
</aside>
