<?php

namespace Tests\Feature;

use App\Models\Sale;
use App\Models\StockMovement;
use App\Models\User;
use App\Services\ReportService;
use App\Support\OutletContext;
use App\Support\Role;
use Tests\PosTestCase;

/**
 * Branch separation: the promise that one outlet can never sell, count, or
 * report on another outlet's goods.
 */
class MultiOutletTest extends PosTestCase
{
    // --- Stock is held per branch ---------------------------------------

    public function test_stock_is_held_per_branch_not_per_product(): void
    {
        $product = $this->makeProduct(['stock' => 40], $this->outletA);

        $this->assertEquals(40, $product->stockAt($this->outletA));
        $this->assertEquals(0, $product->stockAt($this->outletB));

        // The product row caches the chain-wide total.
        $this->assertEquals(40, (float) $product->fresh()->stock);
    }

    public function test_receiving_stock_at_one_branch_leaves_the_other_untouched(): void
    {
        $product = $this->makeProduct(['stock' => 10], $this->outletA);

        app(\App\Services\StockService::class)
            ->apply($product, 25, 'in', null, 'Kiriman', null, $this->outletB->id);

        $product->refresh();

        $this->assertEquals(10, $product->stockAt($this->outletA));
        $this->assertEquals(25, $product->stockAt($this->outletB));
        $this->assertEquals(35, (float) $product->stock);
    }

    public function test_selling_deducts_only_the_selling_branch(): void
    {
        $product = $this->makeProduct(['stock' => 20], $this->outletA);
        app(\App\Services\StockService::class)
            ->apply($product, 20, 'in', null, 'Kiriman', null, $this->outletB->id);

        $this->openShift($this->kasir, $this->outletA);
        $this->actingAs($this->kasir, 'pos');

        $this->postJson('/pos/checkout', [
            'items' => [['product_id' => $product->id, 'qty' => 5]],
            'payments' => [['method' => 'cash', 'amount' => 200000]],
        ])->assertCreated();

        $product->refresh();

        $this->assertEquals(15, $product->stockAt($this->outletA), 'branch A should drop');
        $this->assertEquals(20, $product->stockAt($this->outletB), 'branch B must be untouched');
    }

    public function test_a_branch_cannot_sell_stock_that_only_another_branch_holds(): void
    {
        // All the stock sits at branch B; the cashier works at branch A.
        $product = $this->makeProduct(['stock' => 0], $this->outletA);
        app(\App\Services\StockService::class)
            ->apply($product, 50, 'in', null, 'Kiriman', null, $this->outletB->id);

        $this->openShift($this->kasir, $this->outletA);
        $this->actingAs($this->kasir, 'pos');

        $this->postJson('/pos/checkout', [
            'items' => [['product_id' => $product->id, 'qty' => 1]],
            'payments' => [['method' => 'cash', 'amount' => 50000]],
        ])->assertStatus(422);

        $this->assertSame(0, Sale::withoutGlobalScopes()->count());
        $this->assertEquals(50, $product->fresh()->stockAt($this->outletB));
    }

    // --- Sales and shifts are stamped with their branch -------------------

    public function test_sale_and_its_ledger_row_carry_the_selling_branch(): void
    {
        $product = $this->makeProduct(['stock' => 10], $this->outletA);
        $this->openShift($this->kasir, $this->outletA);
        $this->actingAs($this->kasir, 'pos');

        $this->postJson('/pos/checkout', [
            'items' => [['product_id' => $product->id, 'qty' => 1]],
            'payments' => [['method' => 'cash', 'amount' => 25000]],
        ])->assertCreated();

        $sale = Sale::withoutGlobalScopes()->firstOrFail();
        $this->assertSame($this->outletA->id, $sale->outlet_id);

        $movement = StockMovement::withoutGlobalScopes()->where('type', 'sale')->firstOrFail();
        $this->assertSame($this->outletA->id, $movement->outlet_id);
    }

    public function test_invoice_numbers_carry_the_outlet_code(): void
    {
        $product = $this->makeProduct(['stock' => 10], $this->outletA);
        $this->openShift($this->kasir, $this->outletA);
        $this->actingAs($this->kasir, 'pos');

        $this->postJson('/pos/checkout', [
            'items' => [['product_id' => $product->id, 'qty' => 1]],
            'payments' => [['method' => 'cash', 'amount' => 25000]],
        ])->assertCreated();

        $this->assertStringContainsString(
            '-SAT-',
            Sale::withoutGlobalScopes()->value('invoice_number'),
        );
    }

    public function test_void_returns_stock_to_the_branch_that_sold_it(): void
    {
        $product = $this->makeProduct(['stock' => 10], $this->outletA);
        $this->openShift($this->kasir, $this->outletA);
        $this->actingAs($this->kasir, 'pos');

        $this->postJson('/pos/checkout', [
            'items' => [['product_id' => $product->id, 'qty' => 4]],
            'payments' => [['method' => 'cash', 'amount' => 100000]],
        ])->assertCreated();

        $sale = Sale::withoutGlobalScopes()->firstOrFail();
        $this->assertEquals(6, $product->fresh()->stockAt($this->outletA));

        app(\App\Services\CheckoutService::class)->void($sale, $this->supervisor, 'uji');

        $product->refresh();
        $this->assertEquals(10, $product->stockAt($this->outletA));
        $this->assertEquals(0, $product->stockAt($this->outletB));
    }

    // --- The terminal is confined to the cashier's branch -----------------

    public function test_terminal_shows_only_its_own_branch_stock(): void
    {
        $product = $this->makeProduct(['stock' => 7], $this->outletA);
        app(\App\Services\StockService::class)
            ->apply($product, 99, 'in', null, 'Kiriman', null, $this->outletB->id);

        $this->openShift($this->kasir, $this->outletA);
        $this->actingAs($this->kasir, 'pos');

        $response = $this->getJson('/pos/products');

        $row = collect($response->json('products'))->firstWhere('id', $product->id);

        $this->assertEquals(7, $row['stock'], 'terminal must show branch A stock, not the chain total');
    }

    public function test_cashier_without_a_branch_cannot_open_the_terminal(): void
    {
        $stray = $this->makeUser('nomaden', Role::Kasir, null);

        $this->actingAs($stray, 'pos');

        $this->get('/pos')->assertRedirect(route('pos.login'));
        $this->assertFalse(auth('pos')->check());
    }

    // --- Reporting follows the selected branch ---------------------------

    public function test_reports_are_confined_to_the_selected_branch(): void
    {
        $this->sellAt($this->outletA, 2);   // 2 x 25.000 = 50.000
        $this->sellAt($this->outletB, 4);   // 4 x 25.000 = 100.000

        $today = today()->toDateString();
        $reports = app(ReportService::class);

        $a = $this->actingForOutlet($this->outletA, fn () => $reports->summary($today, $today));
        $b = $this->actingForOutlet($this->outletB, fn () => $reports->summary($today, $today));

        $this->assertEquals(50000, $a['revenue']);
        $this->assertEquals(100000, $b['revenue']);
    }

    public function test_all_outlets_view_sums_every_branch(): void
    {
        $this->sellAt($this->outletA, 2);
        $this->sellAt($this->outletB, 4);

        $today = today()->toDateString();

        app(OutletContext::class)->forget();
        $all = app(ReportService::class)->summary($today, $today);

        $this->assertSame(2, $all['transactions']);
        $this->assertEquals(150000, $all['revenue']);
    }

    public function test_outlet_comparison_breaks_revenue_down_per_branch(): void
    {
        $this->sellAt($this->outletA, 2);
        $this->sellAt($this->outletB, 4);

        app(OutletContext::class)->forget();

        $today = today()->toDateString();
        $rows = app(ReportService::class)->outletPerformance($today, $today)->keyBy('code');

        $this->assertEquals(50000, $rows['SAT']['revenue']);
        $this->assertEquals(100000, $rows['DUA']['revenue']);
    }

    public function test_inventory_report_values_only_the_selected_branch(): void
    {
        $product = $this->makeProduct(['stock' => 3], $this->outletA);
        app(\App\Services\StockService::class)
            ->apply($product, 10, 'in', null, 'Kiriman', null, $this->outletB->id);

        $reports = app(ReportService::class);

        // cost_price is 10.000 in the fixture.
        $a = $this->actingForOutlet($this->outletA, fn () => $reports->inventory());
        $b = $this->actingForOutlet($this->outletB, fn () => $reports->inventory());

        $this->assertEquals(30000, $a['total_value_cost']);
        $this->assertEquals(100000, $b['total_value_cost']);

        app(OutletContext::class)->forget();
        $this->assertEquals(130000, $reports->inventory()['total_value_cost']);
    }

    // --- Registering an operator demands a branch -------------------------

    public function test_registering_an_operator_without_an_outlet_is_rejected(): void
    {
        $this->actingAs($this->owner, 'web');

        $this->post('/dashboard/users', $this->operatorPayload(['outlet_id' => '']))
            ->assertSessionHasErrors('outlet_id');

        $this->assertNull(User::where('username', 'barista')->first());
    }

    public function test_cashier_cannot_be_assigned_to_all_outlets(): void
    {
        $this->actingAs($this->owner, 'web');

        // "all" is the Owner-only option; a cashier must be placed somewhere.
        $this->post('/dashboard/users', $this->operatorPayload([
            'outlet_id' => 'all',
            'role' => Role::Kasir->value,
        ]))->assertSessionHasErrors('outlet_id');

        $this->assertNull(User::where('username', 'barista')->first());
    }

    public function test_operator_is_registered_against_the_chosen_outlet(): void
    {
        $this->actingAs($this->owner, 'web');

        $this->post('/dashboard/users', $this->operatorPayload([
            'outlet_id' => (string) $this->outletB->id,
        ]))->assertRedirect();

        $created = User::where('username', 'barista')->firstOrFail();

        $this->assertSame($this->outletB->id, $created->outlet_id);
    }

    public function test_owner_may_be_assigned_to_all_outlets(): void
    {
        $this->actingAs($this->owner, 'web');

        $this->post('/dashboard/users', $this->operatorPayload([
            'outlet_id' => 'all',
            'role' => Role::Owner->value,
        ]))->assertRedirect();

        $this->assertNull(User::where('username', 'barista')->firstOrFail()->outlet_id);
    }

    public function test_operator_cannot_be_moved_while_a_shift_is_open(): void
    {
        $this->openShift($this->kasir, $this->outletA);

        $this->actingAs($this->owner, 'web');

        $this->put("/dashboard/users/{$this->kasir->id}", [
            'name' => $this->kasir->name,
            'username' => $this->kasir->username,
            'email' => $this->kasir->email,
            'role' => Role::Kasir->value,
            'outlet_id' => (string) $this->outletB->id,
            'is_active' => '1',
        ]);

        $this->assertSame($this->outletA->id, $this->kasir->fresh()->outlet_id,
            'a cashier mid-shift must not be moved between branches');
    }

    // --- Switching branches on the dashboard ------------------------------

    public function test_owner_can_switch_the_dashboard_between_branches(): void
    {
        $this->actingAs($this->owner, 'web');

        $this->post('/dashboard/outlet-switch', ['outlet_id' => $this->outletB->id])
            ->assertRedirect();

        $this->assertSame(
            $this->outletB->id,
            session(\App\Http\Middleware\ResolveTenant::OUTLET_SESSION_KEY),
        );
    }

    public function test_assigned_operator_cannot_switch_branches(): void
    {
        $this->actingAs($this->supervisor, 'web');

        $this->post('/dashboard/outlet-switch', ['outlet_id' => $this->outletB->id]);

        $this->assertNull(session(\App\Http\Middleware\ResolveTenant::OUTLET_SESSION_KEY));
    }

    public function test_supervisor_sees_only_their_own_branch_sales(): void
    {
        $this->sellAt($this->outletA, 2);
        $this->sellAt($this->outletB, 4);

        // The supervisor is pinned to branch A, so the listing must not show
        // branch B's invoice no matter what.
        $branchBInvoice = Sale::withoutGlobalScopes()
            ->where('outlet_id', $this->outletB->id)
            ->value('invoice_number');

        $this->actingAs($this->supervisor, 'web')
            ->get('/dashboard/sales')
            ->assertOk()
            ->assertDontSee($branchBInvoice);
    }

    public function test_only_owner_reaches_outlet_management(): void
    {
        $this->actingAs($this->owner, 'web')->get('/dashboard/outlets')->assertOk();
        $this->actingAs($this->supervisor, 'web')->get('/dashboard/outlets')->assertForbidden();
    }

    // --- Helpers ----------------------------------------------------------

    /** Ring up one sale of `$qty` at the given branch. */
    protected function sellAt(\App\Models\Outlet $outlet, float $qty): Sale
    {
        return $this->actingForOutlet($outlet, function () use ($outlet, $qty) {
            $product = $this->makeProduct([
                'name' => 'Produk '.$outlet->code.'-'.uniqid(),
                'stock' => $qty + 10,
            ], $outlet);

            $cashier = $this->makeUser('kasir'.strtolower($outlet->code).uniqid(), Role::Kasir, $outlet);
            $shift = $this->openShift($cashier, $outlet);

            return app(\App\Services\CheckoutService::class)->checkout(
                $this->tenant, $cashier, $shift,
                [
                    'items' => [['product_id' => $product->id, 'qty' => $qty]],
                    'payments' => [['method' => 'cash', 'amount' => 500000]],
                ],
            );
        });
    }

    /** @return array<string,string> */
    protected function operatorPayload(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Barista Baru',
            'username' => 'barista',
            'email' => 'barista@uji.test',
            'role' => Role::Kasir->value,
            'outlet_id' => (string) $this->outletA->id,
            'password' => 'rahasia123',
            'password_confirmation' => 'rahasia123',
            'is_active' => '1',
        ], $overrides);
    }
}
