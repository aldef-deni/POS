<?php

namespace App\Services;

use App\Models\Product;
use App\Models\StockMovement;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/**
 * The single place stock is allowed to change.
 *
 * Every adjustment writes a ledger row alongside the new balance, so the
 * inventory report can always be reconciled against its own history.
 */
class StockService
{
    /**
     * Apply a signed quantity change to a product.
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
    ): ?StockMovement {
        if (! $product->track_stock || $qty == 0.0) {
            return null;
        }

        return DB::transaction(function () use ($product, $qty, $type, $reference, $note, $userId) {
            // Re-read under a lock so two concurrent sales cannot both read
            // the same starting balance.
            $locked = Product::withoutGlobalScope('tenant')
                ->whereKey($product->getKey())
                ->lockForUpdate()
                ->first();

            $before = (float) $locked->stock;
            $after = $before + $qty;

            $locked->forceFill(['stock' => $after])->save();
            $product->setAttribute('stock', $after);

            return StockMovement::create([
                'tenant_id' => $locked->tenant_id,
                'product_id' => $locked->id,
                'user_id' => $userId,
                'type' => $type,
                'qty' => $qty,
                'stock_before' => $before,
                'stock_after' => $after,
                'unit_cost' => $locked->cost_price,
                'reference_type' => $reference ? class_basename($reference) : null,
                'reference_id' => $reference?->getKey(),
                'note' => $note,
            ]);
        });
    }

    /**
     * Set stock to an absolute counted figure (stock opname) and record the
     * difference as the movement.
     */
    public function setAbsolute(
        Product $product,
        float $countedQty,
        ?string $note = null,
        ?int $userId = null,
    ): ?StockMovement {
        $difference = $countedQty - (float) $product->stock;

        return $this->apply($product, $difference, 'opname', null, $note, $userId);
    }
}
