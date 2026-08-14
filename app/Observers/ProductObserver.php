<?php

namespace App\Observers;

use App\Models\Category;
use App\Models\Product;
use App\Models\Tenant;
use App\Services\SkuGenerator;
use App\Support\Tenancy;

/**
 * Guarantees that every product ends up with an ID, a barcode value and a QR
 * value, no matter where it was created — the dashboard form, an import, or
 * a seeder. Doing it here rather than in the controller is what makes the
 * "otomatis" promise hold everywhere.
 */
class ProductObserver
{
    public function __construct(
        protected SkuGenerator $skus,
    ) {}

    public function creating(Product $product): void
    {
        $tenant = $this->tenantFor($product);

        if (! $tenant) {
            return;
        }

        // Remember the sequence used, so an EAN-13 body can reuse it.
        $sequence = (int) $tenant->sku_next_number;

        if (blank($product->sku)) {
            $category = $product->category_id
                ? Category::withoutGlobalScope('tenant')->find($product->category_id)
                : null;

            $product->sku = $this->skus->generate($tenant, $category);
        }

        if (blank($product->barcode_type)) {
            $product->barcode_type = $tenant->barcode_type ?? 'C128';
        }

        if (blank($product->barcode_value)) {
            $product->barcode_value = $this->skus->barcodeValue(
                $tenant,
                $product->sku,
                $sequence,
            );
        }

        if (blank($product->qr_value)) {
            // The SKU itself is the payload: any QR reader returns something
            // the terminal search box already understands, and it keeps
            // working with no network.
            $product->qr_value = $product->sku;
        }
    }

    /**
     * Products created outside a web request (seeders, console) have no
     * resolved tenant, so fall back to the row the product belongs to.
     */
    protected function tenantFor(Product $product): ?Tenant
    {
        $tenant = app(Tenancy::class)->get();

        if ($tenant && (! $product->tenant_id || $tenant->id === $product->tenant_id)) {
            return $tenant;
        }

        return $product->tenant_id ? Tenant::find($product->tenant_id) : null;
    }
}
