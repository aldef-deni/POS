<?php

namespace Tests\Feature;

use App\Http\Middleware\ResolveTenant;
use App\Models\StockMovement;
use App\Services\StockService;
use Tests\PosTestCase;

/**
 * Receiving goods: the screen an operator reaches for when a shelf is empty,
 * or when a newly opened branch starts with nothing at all.
 */
class RestockTest extends PosTestCase
{
    /** Put the dashboard on branch A, the way the outlet switcher does. */
    protected function onBranchA(): static
    {
        $this->actingAs($this->owner, 'web')
            ->withSession([ResolveTenant::OUTLET_SESSION_KEY => $this->outletA->id]);

        return $this;
    }

    public function test_restock_page_lists_products_that_need_filling_first(): void
    {
        $empty = $this->makeProduct(['name' => 'Kopi Habis', 'stock' => 0, 'min_stock' => 10]);
        $fine = $this->makeProduct(['name' => 'Kopi Aman', 'stock' => 80, 'min_stock' => 10]);

        $response = $this->onBranchA()->get('/dashboard/stock/restock');

        $response->assertOk()
            ->assertSee('Kopi Habis')
            // The default view is "needs restocking", so a healthy shelf is
            // out of the way rather than buried in a long list.
            ->assertDontSee('Kopi Aman');

        $this->onBranchA()->get('/dashboard/stock/restock?show=all')
            ->assertOk()
            ->assertSee('Kopi Habis')
            ->assertSee('Kopi Aman');

        $this->assertNotNull($empty->id);
        $this->assertNotNull($fine->id);
    }

    public function test_restock_adds_stock_to_the_selected_branch_only(): void
    {
        $product = $this->makeProduct(['stock' => 0, 'min_stock' => 10]);

        app(StockService::class)->apply($product, 12, 'in', null, 'awal', null, $this->outletB->id);

        $this->onBranchA()->post('/dashboard/stock/restock', [
            'qty' => [$product->id => 40],
            'note' => 'Faktur 123',
        ])->assertRedirect(route('admin.stock.restock'));

        $product->refresh();

        $this->assertEquals(40, $product->stockAt($this->outletA), 'branch A receives the delivery');
        $this->assertEquals(12, $product->stockAt($this->outletB), 'branch B must be untouched');
    }

    public function test_restock_writes_an_auditable_ledger_row(): void
    {
        $product = $this->makeProduct(['stock' => 5]);

        $this->onBranchA()->post('/dashboard/stock/restock', [
            'qty' => [$product->id => 30],
            'note' => 'Faktur supplier 777',
        ])->assertRedirect();

        $movement = StockMovement::withoutGlobalScopes()
            ->where('product_id', $product->id)
            ->where('note', 'Faktur supplier 777')
            ->firstOrFail();

        $this->assertSame('in', $movement->type);
        $this->assertSame($this->outletA->id, $movement->outlet_id);
        $this->assertEquals(30, (float) $movement->qty);
        $this->assertEquals(5, (float) $movement->stock_before);
        $this->assertEquals(35, (float) $movement->stock_after);
        $this->assertSame($this->owner->id, $movement->user_id);
    }

    public function test_several_products_are_received_in_one_pass(): void
    {
        $a = $this->makeProduct(['name' => 'Produk A', 'stock' => 0]);
        $b = $this->makeProduct(['name' => 'Produk B', 'stock' => 0]);
        $c = $this->makeProduct(['name' => 'Produk C', 'stock' => 0]);

        $this->onBranchA()->post('/dashboard/stock/restock', [
            'qty' => [$a->id => 10, $b->id => 20, $c->id => 5],
        ])->assertRedirect();

        $this->assertEquals(10, $a->refresh()->stockAt($this->outletA));
        $this->assertEquals(20, $b->refresh()->stockAt($this->outletA));
        $this->assertEquals(5, $c->refresh()->stockAt($this->outletA));
    }

    public function test_blank_and_zero_quantities_are_skipped(): void
    {
        $filled = $this->makeProduct(['name' => 'Diisi', 'stock' => 0]);
        $blank = $this->makeProduct(['name' => 'Dikosongkan', 'stock' => 0]);
        $zero = $this->makeProduct(['name' => 'Nol', 'stock' => 0]);

        $this->onBranchA()->post('/dashboard/stock/restock', [
            'qty' => [$filled->id => 7, $blank->id => '', $zero->id => 0],
        ])->assertRedirect();

        $this->assertEquals(7, $filled->refresh()->stockAt($this->outletA));

        // No movement at all for the ones left alone, so the ledger stays
        // an honest record of what actually arrived.
        foreach ([$blank, $zero] as $product) {
            $this->assertSame(0, StockMovement::withoutGlobalScopes()
                ->where('product_id', $product->id)->where('type', 'in')->count());
        }
    }

    public function test_submitting_nothing_is_refused_with_an_explanation(): void
    {
        $product = $this->makeProduct(['stock' => 3]);

        $this->onBranchA()
            ->from('/dashboard/stock/restock')
            ->post('/dashboard/stock/restock', ['qty' => [$product->id => '']])
            ->assertRedirect('/dashboard/stock/restock')
            ->assertSessionHas('error');

        $this->assertEquals(3, $product->refresh()->stockAt($this->outletA));
    }

    public function test_restock_refuses_when_no_branch_is_selected(): void
    {
        $product = $this->makeProduct(['stock' => 0]);

        // The Owner is viewing "all outlets"; a delivery has no destination.
        $this->actingAs($this->owner, 'web')
            ->from('/dashboard/stock/restock')
            ->post('/dashboard/stock/restock', ['qty' => [$product->id => 50]])
            ->assertSessionHas('error');

        $this->assertEquals(0, $product->refresh()->stockAt($this->outletA));
        $this->assertEquals(0, $product->stockAt($this->outletB));
    }

    public function test_page_tells_the_owner_to_pick_a_branch_first(): void
    {
        $this->actingAs($this->owner, 'web')
            ->get('/dashboard/stock/restock')
            ->assertOk()
            ->assertSee('Pilih outlet terlebih dahulu');
    }

    public function test_supervisor_may_restock_their_own_branch(): void
    {
        $product = $this->makeProduct(['stock' => 0]);

        // The supervisor is pinned to branch A, so no switching is needed.
        $this->actingAs($this->supervisor, 'web')
            ->post('/dashboard/stock/restock', ['qty' => [$product->id => 15]])
            ->assertRedirect();

        $this->assertEquals(15, $product->refresh()->stockAt($this->outletA));
    }

    public function test_cashier_cannot_reach_the_restock_screen(): void
    {
        $this->actingAs($this->kasir, 'pos')
            ->get('/dashboard/stock/restock')
            ->assertRedirect(route('admin.login'));
    }

    public function test_negative_quantities_are_rejected(): void
    {
        $product = $this->makeProduct(['stock' => 10]);

        // Restocking only ever adds; removals belong to the adjustment form
        // where the reason is recorded properly.
        $this->onBranchA()->post('/dashboard/stock/restock', [
            'qty' => [$product->id => -5],
        ])->assertSessionHasErrors();

        $this->assertEquals(10, $product->refresh()->stockAt($this->outletA));
    }
}
