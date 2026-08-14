<?php

namespace Tests;

use App\Models\Category;
use App\Models\Product;
use App\Models\Shift;
use App\Models\Tenant;
use App\Models\User;
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

        $this->owner = $this->makeUser('owner', Role::Owner);
        $this->supervisor = $this->makeUser('supervisor', Role::Supervisor);
        $this->kasir = $this->makeUser('kasir', Role::Kasir);

        $this->category = Category::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Minuman',
            'code' => 'MNM',
            'is_active' => true,
        ]);
    }

    protected function makeUser(string $username, Role $role): User
    {
        return User::create([
            'tenant_id' => $this->tenant->id,
            'name' => ucfirst($username).' Uji',
            'username' => $username,
            'email' => $username.'@uji.test',
            'role' => $role,
            'password' => 'rahasia123',
            'pos_pin' => '1234',
            'is_active' => true,
        ]);
    }

    protected function makeProduct(array $attributes = []): Product
    {
        return Product::create(array_merge([
            'tenant_id' => $this->tenant->id,
            'category_id' => $this->category->id,
            'name' => 'Kopi Uji',
            'unit' => 'cup',
            'cost_price' => 10000,
            'price' => 25000,
            'stock' => 100,
            'track_stock' => true,
            'is_active' => true,
        ], $attributes));
    }

    protected function openShift(?User $cashier = null): Shift
    {
        return Shift::create([
            'tenant_id' => $this->tenant->id,
            'user_id' => ($cashier ?? $this->kasir)->id,
            'opened_at' => now(),
            'opening_cash' => 500000,
            'expected_cash' => 500000,
            'status' => 'open',
        ]);
    }
}
