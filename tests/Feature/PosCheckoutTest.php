<?php

namespace Tests\Feature;

use App\Models\Sale;
use Tests\PosTestCase;

/**
 * The money path: pricing, stock, the cash drawer, and the guards that stop
 * a bad basket from being recorded.
 */
class PosCheckoutTest extends PosTestCase
{
    public function test_selling_requires_an_open_shift(): void
    {
        $product = $this->makeProduct();

        $this->actingAs($this->kasir, 'pos');

        $this->postJson('/pos/checkout', [
            'items' => [['product_id' => $product->id, 'qty' => 1]],
            'payments' => [['method' => 'cash', 'amount' => 25000]],
        ])->assertStatus(409);
    }

    public function test_checkout_records_the_sale_and_returns_change(): void
    {
        $product = $this->makeProduct();
        $this->openShift();
        $this->actingAs($this->kasir, 'pos');

        $response = $this->postJson('/pos/checkout', [
            'items' => [['product_id' => $product->id, 'qty' => 2]],
            'payments' => [['method' => 'cash', 'amount' => 100000]],
        ]);

        $response->assertCreated()
            ->assertJson([
                'total' => 50000,
                'paid' => 100000,
                'change' => 50000,
            ]);

        $sale = Sale::firstOrFail();

        $this->assertSame('completed', $sale->status);
        $this->assertEquals(50000, (float) $sale->total);
        // Margin is snapshotted: (25000 - 10000) x 2.
        $this->assertEquals(30000, (float) $sale->profit);
        $this->assertEquals(20000, (float) $sale->cost_total);
        $this->assertStringStartsWith('INV-', $sale->invoice_number);
    }

    public function test_checkout_deducts_stock_through_the_ledger(): void
    {
        $product = $this->makeProduct(['stock' => 10]);
        $this->openShift();
        $this->actingAs($this->kasir, 'pos');

        $this->postJson('/pos/checkout', [
            'items' => [['product_id' => $product->id, 'qty' => 3]],
            'payments' => [['method' => 'cash', 'amount' => 75000]],
        ])->assertCreated();

        $this->assertEquals(7, (float) $product->fresh()->stock);

        $this->assertDatabaseHas('stock_movements', [
            'product_id' => $product->id,
            'type' => 'sale',
            'qty' => -3,
            'stock_before' => 10,
            'stock_after' => 7,
        ]);
    }

    public function test_overselling_is_rejected(): void
    {
        $product = $this->makeProduct(['stock' => 2]);
        $this->openShift();
        $this->actingAs($this->kasir, 'pos');

        $this->postJson('/pos/checkout', [
            'items' => [['product_id' => $product->id, 'qty' => 5]],
            'payments' => [['method' => 'cash', 'amount' => 200000]],
        ])->assertStatus(422);

        $this->assertEquals(2, (float) $product->fresh()->stock);
        $this->assertSame(0, Sale::count());
    }

    public function test_underpayment_is_rejected(): void
    {
        $product = $this->makeProduct();
        $this->openShift();
        $this->actingAs($this->kasir, 'pos');

        $this->postJson('/pos/checkout', [
            'items' => [['product_id' => $product->id, 'qty' => 2]],
            'payments' => [['method' => 'cash', 'amount' => 10000]],
        ])->assertStatus(422);

        $this->assertSame(0, Sale::count());
    }

    public function test_prices_come_from_the_database_not_the_request(): void
    {
        $product = $this->makeProduct(['price' => 25000]);
        $this->openShift();
        $this->actingAs($this->kasir, 'pos');

        // A tampered payload claiming a cheaper unit price must be ignored.
        $this->postJson('/pos/checkout', [
            'items' => [[
                'product_id' => $product->id,
                'qty' => 1,
                'unit_price' => 1,
                'price' => 1,
            ]],
            'payments' => [['method' => 'cash', 'amount' => 25000]],
        ])->assertCreated();

        $this->assertEquals(25000, (float) Sale::firstOrFail()->total);
    }

    public function test_tax_and_rounding_follow_store_settings(): void
    {
        $this->tenant->update([
            'tax_enabled' => true,
            'tax_percent' => 11,
            'tax_inclusive' => false,
            'rounding_mode' => 'nearest_100',
        ]);

        $product = $this->makeProduct(['price' => 25000]);
        $this->openShift();
        $this->actingAs($this->kasir, 'pos');

        $this->postJson('/pos/checkout', [
            'items' => [['product_id' => $product->id, 'qty' => 1]],
            'payments' => [['method' => 'cash', 'amount' => 50000]],
        ])->assertCreated();

        $sale = Sale::firstOrFail();

        // 25.000 + 11% = 27.750, rounded to the nearest 100 => 27.800.
        $this->assertEquals(2750, (float) $sale->tax_amount);
        $this->assertEquals(27800, (float) $sale->total);
        $this->assertEquals(50, (float) $sale->rounding_amount);
    }

    public function test_order_discount_is_applied_and_capped(): void
    {
        $product = $this->makeProduct(['price' => 20000]);
        $this->openShift();
        $this->actingAs($this->kasir, 'pos');

        $this->postJson('/pos/checkout', [
            'items' => [['product_id' => $product->id, 'qty' => 2]],
            'discount_type' => 'percent',
            'discount_value' => 10,
            'payments' => [['method' => 'cash', 'amount' => 40000]],
        ])->assertCreated();

        $sale = Sale::firstOrFail();

        $this->assertEquals(40000, (float) $sale->subtotal);
        $this->assertEquals(4000, (float) $sale->discount_amount);
        $this->assertEquals(36000, (float) $sale->total);
    }

    public function test_split_payment_is_recorded_per_tender(): void
    {
        $product = $this->makeProduct(['price' => 30000]);
        $this->openShift();
        $this->actingAs($this->kasir, 'pos');

        $this->postJson('/pos/checkout', [
            'items' => [['product_id' => $product->id, 'qty' => 2]],
            'payments' => [
                ['method' => 'cash', 'amount' => 20000],
                ['method' => 'qris', 'amount' => 40000],
            ],
        ])->assertCreated();

        $sale = Sale::with('payments')->firstOrFail();

        $this->assertCount(2, $sale->payments);
        $this->assertEquals(60000, (float) $sale->paid_amount);
        // Change never exceeds the cash actually handed over.
        $this->assertEquals(0, (float) $sale->change_amount);
    }

    public function test_shift_totals_track_the_sale(): void
    {
        $product = $this->makeProduct(['price' => 25000]);
        $shift = $this->openShift();
        $this->actingAs($this->kasir, 'pos');

        $this->postJson('/pos/checkout', [
            'items' => [['product_id' => $product->id, 'qty' => 2]],
            'payments' => [['method' => 'cash', 'amount' => 100000]],
        ])->assertCreated();

        $shift->refresh();

        $this->assertEquals(50000, (float) $shift->total_sales);
        $this->assertSame(1, $shift->total_transactions);
        // Opening float plus the cash kept after change was given back.
        $this->assertEquals(550000, (float) $shift->expected_cash);
    }

    public function test_invoice_numbers_are_sequential_and_unique(): void
    {
        $product = $this->makeProduct(['stock' => 50]);
        $this->openShift();
        $this->actingAs($this->kasir, 'pos');

        foreach (range(1, 3) as $i) {
            $this->postJson('/pos/checkout', [
                'items' => [['product_id' => $product->id, 'qty' => 1]],
                'payments' => [['method' => 'cash', 'amount' => 25000]],
            ])->assertCreated();
        }

        $numbers = Sale::orderBy('id')->pluck('invoice_number')->all();

        $this->assertCount(3, array_unique($numbers));
        $this->assertStringEndsWith('0001', $numbers[0]);
        $this->assertStringEndsWith('0003', $numbers[2]);
    }

    public function test_void_restores_stock_and_backs_out_the_shift(): void
    {
        $product = $this->makeProduct(['stock' => 10, 'price' => 25000]);
        $shift = $this->openShift();
        $this->actingAs($this->kasir, 'pos');

        $this->postJson('/pos/checkout', [
            'items' => [['product_id' => $product->id, 'qty' => 4]],
            'payments' => [['method' => 'cash', 'amount' => 100000]],
        ])->assertCreated();

        $sale = Sale::firstOrFail();
        $this->assertEquals(6, (float) $product->fresh()->stock);

        // A cashier cannot approve their own void — a supervisor PIN is required.
        $this->post("/pos/void/{$sale->id}", [
            'reason' => 'Salah input',
            'approver_id' => $this->supervisor->id,
            'pin' => '1234',
        ])->assertRedirect();

        $sale->refresh();

        $this->assertSame('voided', $sale->status);
        $this->assertSame($this->supervisor->id, $sale->voided_by);
        $this->assertEquals(10, (float) $product->fresh()->stock);

        $shift->refresh();
        $this->assertEquals(0, (float) $shift->total_sales);
        $this->assertSame(0, $shift->total_transactions);
    }

    public function test_void_rejects_a_wrong_approver_pin(): void
    {
        $product = $this->makeProduct();
        $this->openShift();
        $this->actingAs($this->kasir, 'pos');

        $this->postJson('/pos/checkout', [
            'items' => [['product_id' => $product->id, 'qty' => 1]],
            'payments' => [['method' => 'cash', 'amount' => 25000]],
        ])->assertCreated();

        $sale = Sale::firstOrFail();

        $this->post("/pos/void/{$sale->id}", [
            'reason' => 'Percobaan',
            'approver_id' => $this->supervisor->id,
            'pin' => '0000',
        ])->assertSessionHasErrors('pin');

        $this->assertSame('completed', $sale->fresh()->status);
    }

    public function test_barcode_lookup_finds_the_product(): void
    {
        $product = $this->makeProduct();
        $this->openShift();
        $this->actingAs($this->kasir, 'pos');

        $this->getJson('/pos/lookup?code='.$product->barcode_value)
            ->assertOk()
            ->assertJson(['found' => true])
            ->assertJsonPath('product.id', $product->id);

        $this->getJson('/pos/lookup?code=TIDAK-ADA')
            ->assertNotFound()
            ->assertJson(['found' => false]);
    }

    public function test_receipt_is_printable_after_the_sale(): void
    {
        $product = $this->makeProduct();
        $this->openShift();
        $this->actingAs($this->kasir, 'pos');

        $this->postJson('/pos/checkout', [
            'items' => [['product_id' => $product->id, 'qty' => 1]],
            'payments' => [['method' => 'cash', 'amount' => 25000]],
        ])->assertCreated();

        $sale = Sale::firstOrFail();

        $this->get(route('pos.receipt', $sale))
            ->assertOk()
            ->assertSee($sale->invoice_number)
            ->assertSee('Toko Uji');
    }
}
