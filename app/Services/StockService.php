<?php

namespace App\Services;

use App\Models\Outlet;
use App\Models\OutletStock;
use App\Models\Product;
use App\Models\StockMovement;
use App\Support\OutletContext;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/**
 * The single place stock is allowed to change.
 *
 * Stock lives per branch in `outlet_stocks`; every adjustment writes a ledger
 * row alongside the new branch balance, and refreshes the cached tenant-wide
 * total on the product so catalogue screens stay cheap to render.
 */
class StockService
{
    /**
     * Apply a signed quantity change to a product at one branch.
     *
     * @param  float  $qty  Positive to add, negative to remove.
     */
    public function apply(
        Product $product,
        float $qty,
        string $type,
        ?Model $reference = null,
        ?string $note = null,
        ?int $userId = null,
        int|Outlet|null $outlet = null,
    ): ?StockMovement {
        if (! $product->track_stock || $qty == 0.0) {
            return null;
        }

        $outletId = $this->resolveOutletId($outlet);

        if (! $outletId) {
            // Refusing here is deliberate: silently writing stock with no
            // branch would corrupt every per-outlet figure downstream.
            throw new \RuntimeException(
                'Perubahan stok memerlukan outlet. Pilih outlet terlebih dahulu.'
            );
        }

        return DB::transaction(function () use ($product, $qty, $type, $reference, $note, $userId, $outletId) {
            $row = $this->lockedRow($product, $outletId);

            $before = (float) $row->stock;
            $after = $before + $qty;

            $row->forceFill(['stock' => $after])->save();

            $movement = StockMovement::create([
                'tenant_id' => $product->tenant_id,
                'outlet_id' => $outletId,
                'product_id' => $product->id,
                'user_id' => $userId,
                'type' => $type,
                'qty' => $qty,
                'stock_before' => $before,
                'stock_after' => $after,
                'unit_cost' => $product->cost_price,
                'reference_type' => $reference ? class_basename($reference) : null,
                'reference_id' => $reference?->getKey(),
                'note' => $note,
            ]);

            $this->refreshProductTotal($product);

            return $movement;
        });
    }

    /**
     * Set a branch's stock to an absolute counted figure (stock opname) and
     * record the difference as the movement.
     */
    public function setAbsolute(
        Product $product,
        float $countedQty,
        ?string $note = null,
        ?int $userId = null,
        int|Outlet|null $outlet = null,
    ): ?StockMovement {
        $outletId = $this->resolveOutletId($outlet);
        $current = $product->stockAt($outletId);

        return $this->apply($product, $countedQty - $current, 'opname', null, $note, $userId, $outletId);
    }

    /**
     * Move stock from one branch to another as a single audited pair of
     * movements, so a transfer can never lose or invent quantity.
     */
    public function transfer(
        Product $product,
        int|Outlet $from,
        int|Outlet $to,
        float $qty,
        ?string $note = null,
        ?int $userId = null,
    ): void {
        $fromId = $this->resolveOutletId($from);
        $toId = $this->resolveOutletId($to);

        if ($fromId === $toId) {
            throw new \InvalidArgumentException('Outlet asal dan tujuan tidak boleh sama.');
        }

        DB::transaction(function () use ($product, $fromId, $toId, $qty, $note, $userId) {
            $this->apply($product, -$qty, 'out', null, $note ?? 'Transfer keluar', $userId, $fromId);
            $this->apply($product, $qty, 'in', null, $note ?? 'Transfer masuk', $userId, $toId);
        });
    }

    /** The branch row for this product, created on first use and locked. */
    protected function lockedRow(Product $product, int $outletId): OutletStock
    {
        $row = OutletStock::withoutGlobalScopes()
            ->where('outlet_id', $outletId)
            ->where('product_id', $product->id)
            ->lockForUpdate()
            ->first();

        if ($row) {
            return $row;
        }

        // A branch that has never carried this product starts at zero, with
        // the product's own reorder point as its default. The coalesce
        // matters: a product saved without a reorder point holds null in
        // memory until refreshed, and the column does not accept null.
        return OutletStock::withoutGlobalScopes()->create([
            'tenant_id' => $product->tenant_id,
            'outlet_id' => $outletId,
            'product_id' => $product->id,
            'stock' => 0,
            'min_stock' => $product->min_stock ?? 0,
        ]);
    }

    /** Keep `products.stock` in step as the sum across every branch. */
    protected function refreshProductTotal(Product $product): void
    {
        $total = OutletStock::withoutGlobalScopes()
            ->where('product_id', $product->id)
            ->sum('stock');

        Product::withoutGlobalScopes()
            ->whereKey($product->id)
            ->update(['stock' => $total]);

        $product->setAttribute('stock', $total);
    }

    protected function resolveOutletId(int|Outlet|null $outlet): ?int
    {
        if ($outlet instanceof Outlet) {
            return $outlet->id;
        }

        return $outlet ?: app(OutletContext::class)->id();
    }
}
