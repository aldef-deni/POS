<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Customer;
use App\Models\Product;
use App\Models\Shift;
use App\Models\Tenant;
use App\Models\User;
use App\Services\CheckoutService;
use App\Support\Role;
use App\Support\Tenancy;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $tenant = $this->tenant();

        // Scope everything that follows to this business, exactly as a web
        // request would, so generated SKUs and stock ledgers line up.
        app(Tenancy::class)->set($tenant);

        $this->users($tenant);
        $categories = $this->categories();
        $this->products($categories);
        $this->customers();
        $this->sampleSales($tenant);

        $this->command->newLine();
        $this->command->info('  Data contoh siap.');
        $this->command->line('  Dashboard : /admin/login');
        $this->command->line('    Owner       owner       / owner123');
        $this->command->line('    Supervisor  supervisor  / super123');
        $this->command->line('  Kasir     : /pos/login');
        $this->command->line('    Kasir 1     kasir1      / kasir123   PIN 1234');
        $this->command->line('    Kasir 2     kasir2      / kasir123   PIN 5678');
        $this->command->newLine();
    }

    protected function tenant(): Tenant
    {
        return Tenant::updateOrCreate(['slug' => 'toko-utama'], [
            'name' => 'Kopi Senja Store',
            'legal_name' => 'CV Kopi Senja Nusantara',
            'business_type' => 'Kafe & Retail',
            'address' => 'Jl. Merdeka No. 88, Kel. Cikini',
            'city' => 'Jakarta Pusat',
            'phone' => '021-555-0188',
            'email' => 'halo@kopisenja.id',
            'website' => 'kopisenja.id',
            'tax_number' => '01.234.567.8-901.000',
            'currency' => 'IDR',
            'currency_symbol' => 'Rp',
            'tax_enabled' => true,
            'tax_percent' => 11.00,
            'tax_inclusive' => false,
            'service_charge_percent' => 0,
            'rounding_mode' => 'nearest_100',
            'receipt_paper' => '80mm',
            'receipt_header' => 'Terima kasih telah berbelanja',
            'receipt_footer' => "Barang yang sudah dibeli dapat ditukar\ndalam 3 hari dengan struk ini.",
            'receipt_show_qr' => true,
            'receipt_show_logo' => true,
            'sku_prefix' => 'KSJ',
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
        ]);
    }

    protected function users(Tenant $tenant): void
    {
        $operators = [
            ['name' => 'Ade Zulham', 'username' => 'owner', 'email' => 'owner@kopisenja.id', 'role' => Role::Owner, 'password' => 'owner123', 'pin' => '9999'],
            ['name' => 'Rina Kartika', 'username' => 'supervisor', 'email' => 'supervisor@kopisenja.id', 'role' => Role::Supervisor, 'password' => 'super123', 'pin' => '4321'],
            ['name' => 'Budi Santoso', 'username' => 'kasir1', 'email' => 'kasir1@kopisenja.id', 'role' => Role::Kasir, 'password' => 'kasir123', 'pin' => '1234'],
            ['name' => 'Siti Aminah', 'username' => 'kasir2', 'email' => 'kasir2@kopisenja.id', 'role' => Role::Kasir, 'password' => 'kasir123', 'pin' => '5678'],
        ];

        foreach ($operators as $operator) {
            User::updateOrCreate(['username' => $operator['username']], [
                'tenant_id' => $tenant->id,
                'name' => $operator['name'],
                'email' => $operator['email'],
                'role' => $operator['role'],
                'password' => $operator['password'],
                'pos_pin' => $operator['pin'],
                'is_active' => true,
            ]);
        }
    }

    /** @return array<string,Category> */
    protected function categories(): array
    {
        $definitions = [
            ['name' => 'Kopi', 'code' => 'KOP', 'color' => '#8B5CF6'],
            ['name' => 'Non-Kopi', 'code' => 'NKP', 'color' => '#0EA5E9'],
            ['name' => 'Makanan', 'code' => 'MKN', 'color' => '#F59E0B'],
            ['name' => 'Pastry', 'code' => 'PST', 'color' => '#EC4899'],
            ['name' => 'Merchandise', 'code' => 'MCH', 'color' => '#10B981'],
        ];

        $categories = [];

        foreach ($definitions as $index => $definition) {
            $categories[$definition['name']] = Category::updateOrCreate(
                ['name' => $definition['name']],
                $definition + ['sort_order' => $index, 'is_active' => true],
            );
        }

        return $categories;
    }

    /** @param array<string,Category> $categories */
    protected function products(array $categories): void
    {
        $catalogue = [
            ['Kopi', 'Espresso', 8000, 18000, 'cup', 120, true],
            ['Kopi', 'Americano', 9000, 22000, 'cup', 120, true],
            ['Kopi', 'Cappuccino', 11000, 28000, 'cup', 100, true],
            ['Kopi', 'Caffe Latte', 11500, 29000, 'cup', 100, true],
            ['Kopi', 'Kopi Susu Gula Aren', 10000, 25000, 'cup', 150, true],
            ['Kopi', 'Cold Brew 250ml', 13000, 32000, 'btl', 45, false],
            ['Kopi', 'Biji Kopi Arabika 200g', 55000, 95000, 'pack', 30, false],
            ['Non-Kopi', 'Matcha Latte', 13000, 30000, 'cup', 80, true],
            ['Non-Kopi', 'Cokelat Panas', 10000, 26000, 'cup', 80, false],
            ['Non-Kopi', 'Teh Tarik', 8000, 20000, 'cup', 90, false],
            ['Non-Kopi', 'Lemon Tea', 7000, 18000, 'cup', 90, false],
            ['Non-Kopi', 'Air Mineral 600ml', 3000, 7000, 'btl', 200, false],
            ['Makanan', 'Nasi Goreng Spesial', 18000, 38000, 'porsi', 40, true],
            ['Makanan', 'Mie Goreng Jawa', 15000, 33000, 'porsi', 40, false],
            ['Makanan', 'Chicken Katsu Rice', 22000, 45000, 'porsi', 35, true],
            ['Makanan', 'French Fries', 9000, 22000, 'porsi', 60, false],
            ['Makanan', 'Roti Bakar Cokelat', 8000, 20000, 'porsi', 50, false],
            ['Pastry', 'Croissant Butter', 9000, 24000, 'pcs', 24, true],
            ['Pastry', 'Pain au Chocolat', 10000, 27000, 'pcs', 20, false],
            ['Pastry', 'Cheese Cake Slice', 14000, 35000, 'slice', 18, true],
            ['Pastry', 'Banana Bread', 8000, 21000, 'slice', 22, false],
            ['Pastry', 'Cinnamon Roll', 9500, 25000, 'pcs', 20, false],
            ['Merchandise', 'Tumbler Stainless 500ml', 65000, 145000, 'pcs', 15, false],
            ['Merchandise', 'Tote Bag Kanvas', 35000, 85000, 'pcs', 20, false],
            ['Merchandise', 'Mug Keramik Logo', 28000, 65000, 'pcs', 25, false],
        ];

        foreach ($catalogue as [$categoryName, $name, $cost, $price, $unit, $stock, $favorite]) {
            $product = Product::updateOrCreate(
                ['name' => $name],
                [
                    'category_id' => $categories[$categoryName]->id,
                    'cost_price' => $cost,
                    'price' => $price,
                    'unit' => $unit,
                    'min_stock' => 10,
                    'track_stock' => true,
                    'is_active' => true,
                    'is_favorite' => $favorite,
                ],
            );

            // Seed the opening balance through the ledger so the stock report
            // reconciles against its own history.
            if ($product->stockMovements()->doesntExist()) {
                app(\App\Services\StockService::class)->apply(
                    $product,
                    $stock,
                    'in',
                    null,
                    'Stok awal (data contoh)',
                );
            }
        }
    }

    protected function customers(): void
    {
        $people = [
            ['Andi Pratama', '081234567801'],
            ['Maya Lestari', '081234567802'],
            ['Dimas Nugroho', '081234567803'],
            ['Sari Wulandari', '081234567804'],
            ['Fajar Ramadhan', '081234567805'],
        ];

        foreach ($people as $index => [$name, $phone]) {
            Customer::updateOrCreate(['phone' => $phone], [
                'code' => 'MBR-'.str_pad((string) ($index + 1), 4, '0', STR_PAD_LEFT),
                'name' => $name,
                'is_member' => true,
            ]);
        }
    }

    /**
     * Generate three weeks of believable transactions so every report and
     * chart has something to show on first run.
     */
    protected function sampleSales(Tenant $tenant): void
    {
        if (\App\Models\Sale::exists()) {
            return;
        }

        $checkout = app(CheckoutService::class);
        $cashiers = User::where('role', Role::Kasir->value)->get();
        $products = Product::active()->get();
        $customers = Customer::pluck('id')->all();
        $methods = ['cash', 'cash', 'cash', 'qris', 'qris', 'card', 'transfer'];

        $stockService = app(\App\Services\StockService::class);

        for ($dayOffset = 20; $dayOffset >= 0; $dayOffset--) {
            $date = Carbon::today()->subDays($dayOffset);

            // Morning delivery: top up anything that ran low yesterday, so
            // three weeks of trading never starves the catalogue.
            foreach ($products as $product) {
                $product->refresh();

                if ((float) $product->stock < 40) {
                    $stockService->apply(
                        $product,
                        150 - (float) $product->stock,
                        'in',
                        null,
                        'Penerimaan barang '.$date->format('d/m/Y'),
                    );
                }
            }

            foreach ($cashiers as $cashier) {
                $shift = Shift::create([
                    'tenant_id' => $tenant->id,
                    'user_id' => $cashier->id,
                    'opened_at' => $date->copy()->setTime(8, 0),
                    'opening_cash' => 500000,
                    'expected_cash' => 500000,
                    'status' => 'open',
                ]);

                // Weekends are busier than weekdays.
                $transactions = $date->isWeekend() ? random_int(9, 16) : random_int(5, 11);

                for ($i = 0; $i < $transactions; $i++) {
                    $items = $products->random(random_int(1, 4))
                        ->map(fn (Product $p) => [
                            'product_id' => $p->id,
                            'qty' => random_int(1, 3),
                        ])->values()->all();

                    $priced = $checkout->calculate($tenant, $items);
                    $total = $priced['totals']['total'];

                    if ($total <= 0) {
                        continue;
                    }

                    $method = $methods[array_rand($methods)];

                    // Cash customers hand over a rounded-up note.
                    $amount = $method === 'cash'
                        ? (float) (ceil($total / 10000) * 10000)
                        : $total;

                    try {
                        $sale = $checkout->checkout($tenant, $cashier, $shift, [
                            'items' => $items,
                            'payments' => [['method' => $method, 'amount' => $amount]],
                            'customer_id' => random_int(1, 100) <= 35 ? $customers[array_rand($customers)] : null,
                        ]);

                        // Spread transactions across trading hours.
                        $at = $date->copy()->setTime(random_int(8, 20), random_int(0, 59));
                        $sale->forceFill(['created_at' => $at, 'updated_at' => $at])->save();
                    } catch (\Throwable $e) {
                        // Out of stock on a random basket — skip and continue.
                        continue;
                    }
                }

                $shift->refresh();

                // Close every shift except today's, leaving one live drawer.
                if ($dayOffset > 0) {
                    $expected = $shift->expectedCashNow();
                    $variance = random_int(0, 100) <= 70 ? 0 : random_int(-25000, 25000);

                    $shift->forceFill([
                        'closed_at' => $date->copy()->setTime(21, 0),
                        'counted_cash' => $expected + $variance,
                        'expected_cash' => $expected,
                        'cash_variance' => $variance,
                        'closed_by' => $cashier->id,
                        'status' => 'closed',
                    ])->save();
                }
            }
        }
    }
}
