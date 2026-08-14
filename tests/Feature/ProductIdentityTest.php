<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Services\SkuGenerator;
use Tests\PosTestCase;

/**
 * Requirement 7 and 8: every finished product gets an ID, a barcode and a QR
 * automatically, following the pattern the Owner configured.
 */
class ProductIdentityTest extends PosTestCase
{
    public function test_new_product_receives_id_barcode_and_qr_automatically(): void
    {
        $product = $this->makeProduct();

        $this->assertNotEmpty($product->sku);
        $this->assertNotEmpty($product->barcode_value);
        $this->assertNotEmpty($product->qr_value);

        // Prefix - category code - sequence, with the date segment disabled.
        $this->assertSame('TST-MNM-0001', $product->sku);
        $this->assertSame($product->sku, $product->barcode_value);
        $this->assertSame($product->sku, $product->qr_value);
    }

    public function test_sequence_advances_and_never_repeats(): void
    {
        $first = $this->makeProduct(['name' => 'Produk A']);
        $second = $this->makeProduct(['name' => 'Produk B']);
        $third = $this->makeProduct(['name' => 'Produk C']);

        $this->assertSame('TST-MNM-0001', $first->sku);
        $this->assertSame('TST-MNM-0002', $second->sku);
        $this->assertSame('TST-MNM-0003', $third->sku);

        $this->assertSame(3, Product::distinct()->count('sku'));
    }

    public function test_pattern_follows_the_configured_mechanism(): void
    {
        $this->tenant->update([
            'sku_prefix' => 'KSR',
            'sku_separator' => '.',
            'sku_include_category' => false,
            'sku_date_segment' => 'yy',
            'sku_sequence_length' => 6,
        ]);

        $product = $this->makeProduct();

        $this->assertSame('KSR.'.now()->format('y').'.000001', $product->sku);
    }

    public function test_ean13_barcode_is_thirteen_digits_with_a_valid_check_digit(): void
    {
        $this->tenant->update(['barcode_type' => 'EAN13']);

        $product = $this->makeProduct();

        $this->assertSame('EAN13', $product->barcode_type);
        $this->assertMatchesRegularExpression('/^\d{13}$/', $product->barcode_value);

        // Recompute the checksum over the first 12 digits.
        $body = substr($product->barcode_value, 0, 12);
        $expected = app(SkuGenerator::class)->ean13CheckDigit($body);

        $this->assertSame((string) $expected, substr($product->barcode_value, -1));
    }

    public function test_manually_supplied_id_is_respected(): void
    {
        $product = $this->makeProduct(['sku' => 'MANUAL-001']);

        $this->assertSame('MANUAL-001', $product->sku);
    }

    public function test_barcode_and_qr_images_render(): void
    {
        $product = $this->makeProduct();

        $this->actingAs($this->owner, 'web');

        $barcode = $this->get(route('media.barcode', $product));
        $barcode->assertOk()->assertHeader('Content-Type', 'image/svg+xml');
        $this->assertStringContainsString('<svg', $barcode->getContent());

        $qr = $this->get(route('media.qr', $product));
        $qr->assertOk()->assertHeader('Content-Type', 'image/svg+xml');
        $this->assertStringContainsString('<svg', $qr->getContent());
    }

    public function test_media_is_not_public(): void
    {
        $product = $this->makeProduct();

        $this->get(route('media.barcode', $product))->assertForbidden();
    }

    public function test_creating_a_product_through_the_dashboard_records_opening_stock(): void
    {
        $this->actingAs($this->owner, 'web');

        $this->post('/dashboard/products', [
            'name' => 'Teh Manis',
            'category_id' => $this->category->id,
            'unit' => 'cup',
            'cost_price' => 5000,
            'price' => 12000,
            'stock' => 40,
            'min_stock' => 5,
            'track_stock' => '1',
            'is_active' => '1',
        ])->assertRedirect();

        $product = Product::where('name', 'Teh Manis')->firstOrFail();

        $this->assertSame('TST-MNM-0001', $product->sku);
        $this->assertEquals(40, (float) $product->stock);

        // Opening stock must arrive through the ledger, not a bare column write.
        $this->assertDatabaseHas('stock_movements', [
            'product_id' => $product->id,
            'type' => 'in',
            'qty' => 40,
        ]);
    }
}
