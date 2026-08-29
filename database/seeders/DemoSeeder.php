<?php

namespace Database\Seeders;

use App\Models\Customer;
use App\Models\Outlet;
use App\Models\Product;
use App\Models\Sale;
use App\Models\Shift;
use App\Models\Tenant;
use App\Models\User;
use App\Services\CheckoutService;
use App\Services\StockService;
use App\Support\OutletContext;
use App\Support\Role;
use App\Support\Tenancy;
use Illuminate\Support\Carbon;

/**
 * Isi contoh untuk tenant demo.
 *
 * Mewarisi DatabaseSeeder supaya katalog produk, kategori, dan pelanggannya
 * persis sama dengan yang dipakai pengembangan - satu sumber, tidak ada dua
 * daftar yang lama-lama berbeda isinya.
 *
 * Yang ditimpa hanya bagian yang harus berbeda: identitas tenant, akun
 * penggunanya, jumlah outlet, dan volume transaksi.
 */
class DemoSeeder extends DatabaseSeeder
{
    /**
     * Dijalankan tanpa keluaran ke konsol.
     *
     * Pemulihan demo bisa terpicu dari permintaan HTTP saat ada yang masuk,
     * dan di sana $this->command tidak ada - memanggilnya akan menghentikan
     * seluruh permintaan.
     */
    public function run(): void
    {
        $tenant = $this->tenant();

        app(Tenancy::class)->set($tenant);

        $outlets = $this->outlets();
        $this->users($tenant, $outlets);
        $categories = $this->categories();
        $this->products($categories, $outlets);
        $this->customers();
        $this->sampleSales($tenant, $outlets);

        app(OutletContext::class)->forget();
        app(Tenancy::class)->forget();
    }

    protected function tenant(): Tenant
    {
        return Tenant::withoutGlobalScopes()->updateOrCreate(
            ['slug' => (string) config('demo.tenant_slug')],
            [
                'name' => (string) config('demo.tenant_name'),
                'legal_name' => 'Aldef Tech Demo',
                'business_type' => 'Kafe & Retail',
                'address' => 'Jl. Contoh No. 1',
                'city' => 'Jakarta',
                'phone' => '021-000-0000',
                'email' => (string) config('demo.email'),
                'website' => 'aldeftech.com',
                'currency' => 'IDR',
                'currency_symbol' => 'Rp',
                'tax_enabled' => true,
                'tax_percent' => 11.00,
                'tax_inclusive' => false,
                'service_charge_percent' => 0,
                'rounding_mode' => 'nearest_100',
                'receipt_paper' => '80mm',
                'receipt_header' => 'Terima kasih telah mencoba demo',
                'receipt_footer' => "Ini akun demo.\nSeluruh isinya dikembalikan setiap 24 jam.",
                'receipt_show_qr' => true,
                'receipt_show_logo' => false,
                'sku_prefix' => 'DEMO',
                'sku_separator' => '-',
                'sku_include_category' => true,
                'sku_date_segment' => 'yymm',
                'sku_sequence_length' => 4,
                'sku_next_number' => 1,
                'barcode_type' => 'C128',
                'low_stock_threshold' => 5,
                'allow_negative_stock' => false,
                'plan' => 'pro',
                'plan_expires_at' => Carbon::now()->addYear(),
                'is_active' => true,
            ],
        );
    }

    /** @return array<string,Outlet> */
    protected function outlets(): array
    {
        $definitions = [
            ['name' => 'Demo Outlet Pusat', 'code' => 'DMO', 'address' => 'Jl. Contoh No. 1', 'city' => 'Jakarta', 'phone' => '021-000-0000', 'is_default' => true],
            ['name' => 'Demo Outlet Cabang', 'code' => 'DMC', 'address' => 'Jl. Contoh No. 2', 'city' => 'Bandung', 'phone' => '022-000-0000', 'is_default' => false],
        ];

        $definitions = array_slice($definitions, 0, max(1, (int) config('demo.outlets')));

        $outlets = [];

        foreach ($definitions as $index => $definition) {
            $outlets[$definition['code']] = Outlet::updateOrCreate(
                ['code' => $definition['code']],
                $definition + ['is_active' => true, 'sort_order' => $index],
            );
        }

        return $outlets;
    }

    /**
     * Akun demo memakai username tersendiri.
     *
     * Kolom username unik lintas tenant, jadi memakai 'owner' seperti seeder
     * pengembangan akan bentrok dengan akun pelanggan yang sudah ada.
     *
     * @param  array<string,Outlet>  $outlets
     */
    protected function users(Tenant $tenant, array $outlets): void
    {
        $utama = array_key_first($outlets);

        $operators = [
            [
                'name' => (string) config('demo.name'),
                'username' => (string) config('demo.username'),
                'email' => (string) config('demo.email'),
                'role' => Role::Owner,
                'password' => (string) config('demo.password'),
                'pin' => '9999',
                'outlet' => null,
            ],
            [
                'name' => 'Supervisor Demo',
                'username' => config('demo.username').'-spv',
                'email' => 'spv.'.config('demo.email'),
                'role' => Role::Supervisor,
                'password' => (string) config('demo.password'),
                'pin' => '4321',
                'outlet' => $utama,
            ],
        ];

        // Satu kasir untuk setiap outlet. Tanpa ini outlet kedua tidak punya
        // siapa pun yang bertransaksi, dan laporan perbandingan cabang -
        // salah satu hal yang ingin diperlihatkan demo - tampil kosong.
        $pin = ['1234', '5678', '2468'];

        foreach (array_keys($outlets) as $i => $kode) {
            $operators[] = [
                'name' => 'Kasir Demo '.($i + 1),
                'username' => config('demo.username').'-kasir'.($i + 1),
                'email' => 'kasir'.($i + 1).'.'.config('demo.email'),
                'role' => Role::Kasir,
                'password' => (string) config('demo.password'),
                'pin' => $pin[$i] ?? (string) (1000 + $i),
                'outlet' => $kode,
            ];
        }

        foreach ($operators as $operator) {
            User::withoutGlobalScopes()->updateOrCreate(
                ['username' => $operator['username']],
                [
                    'tenant_id' => $tenant->id,
                    'outlet_id' => $operator['outlet'] ? $outlets[$operator['outlet']]->id : null,
                    'name' => $operator['name'],
                    'email' => $operator['email'],
                    'role' => $operator['role'],
                    'password' => $operator['password'],
                    'pos_pin' => $operator['pin'],
                    'is_active' => true,
                ],
            );
        }
    }

    /**
     * Riwayat penjualan singkat.
     *
     * Versi induknya membangun tiga minggu perdagangan di tiga outlet. Itu
     * pantas untuk seeder pengembangan, tetapi terlalu berat di sini karena
     * pemulihan ini bisa terpicu saat seseorang menekan tombol Masuk.
     *
     * @param  array<string,Outlet>  $outlets
     */
    protected function sampleSales(Tenant $tenant, array $outlets): void
    {
        // Pemeriksaan induknya melihat seluruh penjualan lintas tenant, yang
        // di produksi berarti data contoh tidak akan pernah dibuat.
        if (Sale::withoutGlobalScopes()->where('tenant_id', $tenant->id)->exists()) {
            return;
        }

        $checkout = app(CheckoutService::class);
        $stock = app(StockService::class);
        $context = app(OutletContext::class);

        $products = Product::active()->get();
        $customers = Customer::pluck('id')->all();
        $methods = ['cash', 'cash', 'qris', 'qris', 'card', 'transfer'];

        $hari = max(1, (int) config('demo.sales_days'));
        $maksimum = max(1, (int) config('demo.max_sales_per_day'));

        foreach ($outlets as $outlet) {
            $context->set($outlet);

            $kasir = User::where('role', Role::Kasir->value)
                ->where('outlet_id', $outlet->id)
                ->first();

            if (! $kasir) {
                continue;
            }

            for ($mundur = $hari - 1; $mundur >= 0; $mundur--) {
                $tanggal = Carbon::today()->subDays($mundur);

                $shift = Shift::create([
                    'tenant_id' => $tenant->id,
                    'outlet_id' => $outlet->id,
                    'user_id' => $kasir->id,
                    'opened_at' => $tanggal->copy()->setTime(8, 0),
                    'opening_cash' => 500000,
                    'expected_cash' => 500000,
                    'status' => 'open',
                ]);

                $jumlah = random_int(max(1, $maksimum - 3), $maksimum);

                for ($i = 0; $i < $jumlah; $i++) {
                    $items = $products->random(random_int(1, 3))
                        ->map(fn (Product $p) => [
                            'product_id' => $p->id,
                            'qty' => random_int(1, 2),
                        ])->values()->all();

                    $priced = $checkout->calculate($tenant, $items);
                    $total = $priced['totals']['total'];

                    if ($total <= 0) {
                        continue;
                    }

                    $metode = $methods[array_rand($methods)];
                    $dibayar = $metode === 'cash' ? (float) (ceil($total / 10000) * 10000) : $total;

                    try {
                        $sale = $checkout->checkout($tenant, $kasir, $shift, [
                            'items' => $items,
                            'payments' => [['method' => $metode, 'amount' => $dibayar]],
                            'customer_id' => $customers && random_int(1, 100) <= 35
                                ? $customers[array_rand($customers)]
                                : null,
                        ]);

                        $waktu = $tanggal->copy()->setTime(random_int(8, 20), random_int(0, 59));
                        $sale->forceFill(['created_at' => $waktu, 'updated_at' => $waktu])->save();
                    } catch (\Throwable $e) {
                        // Stok habis di tengah jalan bukan kegagalan pemulihan;
                        // transaksi itu saja yang dilewati.
                        continue;
                    }
                }

                $shift->refresh();

                // Shift hari ini sengaja dibiarkan terbuka, supaya terminal
                // kasir punya laci aktif saat demo dibuka.
                if ($mundur > 0) {
                    $diharapkan = $shift->expectedCashNow();
                    $selisih = random_int(0, 100) <= 70 ? 0 : random_int(-20000, 20000);

                    $shift->forceFill([
                        'closed_at' => $tanggal->copy()->setTime(21, 0),
                        'counted_cash' => $diharapkan + $selisih,
                        'expected_cash' => $diharapkan,
                        'cash_variance' => $selisih,
                        'closed_by' => $kasir->id,
                        'status' => 'closed',
                    ])->save();
                }
            }
        }

        $context->forget();
    }
}
