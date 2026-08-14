<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOutlet;
use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * How much of one product one branch holds. The authoritative stock figure —
 * `products.stock` is only a cached total across branches.
 */
class OutletStock extends Model
{
    use BelongsToOutlet, BelongsToTenant;

    protected $fillable = [
        'tenant_id', 'outlet_id', 'product_id', 'stock', 'min_stock',
    ];

    protected function casts(): array
    {
        return [
            'stock' => 'decimal:3',
            'min_stock' => 'decimal:3',
        ];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function isLow(): bool
    {
        return (float) $this->stock <= (float) $this->min_stock;
    }

    public function isOut(): bool
    {
        return (float) $this->stock <= 0;
    }
}
