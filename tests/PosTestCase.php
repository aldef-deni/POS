<?php

namespace Tests;

use App\Models\Category;
use App\Models\Outlet;
use App\Models\Product;
use App\Models\Shift;
use App\Models\Tenant;
use App\Models\User;
use App\Support\OutletContext;
use App\Support\Role;
use App\Support\Tenancy;
use Illuminate\Foundation\Testing\RefreshDatabase;

/**
 * Shared fixture: one tenant, one operator per role, a category and a
 * couple of products. Individual tests tweak what they need on top.
 */
abstract class PosTestCase extends TestCase
{
    use RefreshDatabase;

    protected Tenant $tenant;

    protected User $owner;

    protected User $supervisor;

    protected User $kasir;

    protected Category $category;

    protected Outlet $outletA;

    protected Outlet $outletB;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::create([
            'name' => 'Toko Uji',
            'slug' => 'toko-uji',
            'currency_symbol' => 'Rp',
            'tax_enabled' => false,
            'tax_percent' => 11,
            'rounding_mode' => 'none',
            'sku_prefix' => 'TST',
            'sku_separator' => '-',
            'sku_include_category' => true,
            'sku_date_segment' => 'none',
            'sku_sequence_length' => 4,
            'sku_next_number' => 1,
            'barcode_type' => 'C128',
            'is_active' => true,
        ]);

        // Mirror what ResolveTenant does for a real request.
        app(Tenancy::class)->set($this->tenant);

        // Two branches, so every test can prove one cannot reach the other.
        $this->outletA = Outlet::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Outlet Satu',
            'code' => 'SAT',
            'is_active' => true,
            'is_default' => true,
            'sort_order' => 0,
        ]);

        $this->outletB = Outlet::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Outlet Dua',
            'code' => 'DUA',
            'is_active' => true,
            'is_default' => false,
            'sort_order' => 1,
        ]);

        // The Owner oversees everything; the rest are pinned to branch A.
        $this->owner = $this->makeUser('owner', Role::Owner, null);
        $this->supervisor = $this->makeUser('supervisor', Role::Supervisor, $this->outletA);
        $this->kasir = $this->makeUser('kasir', Role::Kasir, $this->outletA);

        $this->category = Category::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Minuman',
            'code' => 'MNM',
            'is_active' => true,
        ]);
    }

    protected function makeUser(string $username, Role $role, ?Outlet $outlet = null): User
    {
        return User::create([
            'tenant_id' => $this->tenant->id,
            'outlet_id' => $outlet?->id,
            'name' => ucfirst($username).' Uji',
            'username' => $username,
            'email' => $username.'@uji.test',
            'role' => $role,
            'password' => 'rahasia123',
            'pos_pin' => '1234',
            'is_active' => true,
        ]);
    }

    /**
     * A product with opening stock. Stock is per branch, so it is written
     * through the ledger for the outlet given (branch A by default).
     */
    protected function makeProduct(array $attributes = [], ?Outlet $outlet = null): Product
    {
        $opening = $attributes['stock'] ?? 100;
        unset($attributes['stock']);

        $product = Product::create(array_merge([
            'tenant_id' => $this->tenant->id,
            'category_id' => $this->category->id,
            'name' => 'Kopi Uji',
            'unit' => 'cup',
            'cost_price' => 10000,
            'price' => 25000,
            'track_stock' => true,
            'is_active' => true,
        ], $attributes));

        if ($opening > 0) {
            app(\App\Services\StockService::class)->apply(
                $product, (float) $opening, 'in', null, 'Stok awal uji', null,
                ($outlet ?? $this->outletA)->id,
            );
        }

        return $product->refresh();
    }

    protected function openShift(?User $cashier = null, ?Outlet $outlet = null): Shift
    {
        $cashier ??= $this->kasir;

        return Shift::create([
            'tenant_id' => $this->tenant->id,
            'outlet_id' => ($outlet?->id) ?? $cashier->outlet_id ?? $this->outletA->id,
            'user_id' => $cashier->id,
            'opened_at' => now(),
            'opening_cash' => 500000,
            'expected_cash' => 500000,
            'status' => 'open',
        ]);
    }

    /** Run a callback as if the request were scoped to one branch. */
    protected function actingForOutlet(Outlet $outlet, callable $callback): mixed
    {
        $context = app(OutletContext::class);
        $previous = $context->get();

        $context->set($outlet);

        try {
            return $callback();
        } finally {
            $context->set($previous);
        }
    }
}
