<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use App\Observers\ProductObserver;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[ObservedBy(ProductObserver::class)]
class Product extends Model
{
    use BelongsToTenant, SoftDeletes;

    protected $fillable = [
        'tenant_id', 'category_id', 'sku', 'name', 'description', 'unit',
        'cost_price', 'price', 'wholesale_price', 'min_wholesale_qty', 'tax_exempt',
        'track_stock', 'stock', 'min_stock',
        'barcode_value', 'barcode_type', 'qr_value',
        'image_path', 'is_active', 'is_favorite',
    ];

    protected function casts(): array
    {
        return [
            'cost_price' => 'decimal:2',
            'price' => 'decimal:2',
            'wholesale_price' => 'decimal:2',
            'stock' => 'decimal:3',
            'min_stock' => 'decimal:3',
            'tax_exempt' => 'boolean',
            'track_stock' => 'boolean',
            'is_active' => 'boolean',
            'is_favorite' => 'boolean',
            'min_wholesale_qty' => 'integer',
            'sold_count' => 'integer',
        ];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function stockMovements(): HasMany
    {
        return $this->hasMany(StockMovement::class);
    }

    /**
     * Per-branch stock rows. `products.stock` is only a cached total across
     * these — this relation is the authoritative figure.
     */
    public function outletStocks(): HasMany
    {
        return $this->hasMany(OutletStock::class);
    }

    /** Stock held by one branch, 0 when the branch has never carried it. */
    public function stockAt(int|Outlet|null $outlet): float
    {
        $outletId = $outlet instanceof Outlet ? $outlet->id : $outlet;

        if (! $outletId) {
            return (float) $this->stock;
        }

        // Uses the already-loaded relation when the caller eager-loaded it,
        // so a product grid does not fire a query per row.
        if ($this->relationLoaded('outletStocks')) {
            $row = $this->outletStocks->firstWhere('outlet_id', $outletId);

            return $row ? (float) $row->stock : 0.0;
        }

        return (float) ($this->outletStocks()
            ->withoutGlobalScope('outlet')
            ->where('outlet_id', $outletId)
            ->value('stock') ?? 0);
    }

    /** Reorder point for one branch, falling back to the product default. */
    public function minStockAt(int|Outlet|null $outlet): float
    {
        $outletId = $outlet instanceof Outlet ? $outlet->id : $outlet;

        if (! $outletId) {
            return (float) $this->min_stock;
        }

        if ($this->relationLoaded('outletStocks')) {
            $row = $this->outletStocks->firstWhere('outlet_id', $outletId);

            return $row ? (float) $row->min_stock : (float) $this->min_stock;
        }

        return (float) ($this->outletStocks()
            ->withoutGlobalScope('outlet')
            ->where('outlet_id', $outletId)
            ->value('min_stock') ?? $this->min_stock);
    }

    public function saleItems(): HasMany
    {
        return $this->hasMany(SaleItem::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /**
     * Products at or below their reorder point.
     *
     * With a branch selected this compares that branch's shelf; with "all
     * outlets" it compares the chain-wide total, because a product can be
     * comfortable overall while one outlet has run dry.
     */
    public function scopeLowStock(Builder $query): Builder
    {
        $outletId = app(\App\Support\OutletContext::class)->id();

        $query->where('track_stock', true);

        if (! $outletId) {
            return $query->whereColumn('stock', '<=', 'min_stock');
        }

        return $query->whereHas('outletStocks', fn ($q) => $q
            ->withoutGlobalScope('outlet')
            ->where('outlet_id', $outletId)
            ->whereColumn('stock', '<=', 'min_stock'));
    }

    /** Free-text lookup used by the terminal search box. */
    public function scopeSearch(Builder $query, ?string $term): Builder
    {
        $term = trim((string) $term);

        if ($term === '') {
            return $query;
        }

        return $query->where(function (Builder $q) use ($term) {
            $q->where('name', 'like', "%{$term}%")
                ->orWhere('sku', 'like', "%{$term}%")
                ->orWhere('barcode_value', 'like', "%{$term}%");
        });
    }

    public function imageUrl(): ?string
    {
        return $this->image_path
            ? \Illuminate\Support\Facades\Storage::disk('public')->url($this->image_path)
            : null;
    }

    /** Unit price for a given quantity, honouring the wholesale break. */
    public function priceForQty(float $qty): float
    {
        if ($this->wholesale_price !== null
            && $this->min_wholesale_qty > 0
            && $qty >= $this->min_wholesale_qty) {
            return (float) $this->wholesale_price;
        }

        return (float) $this->price;
    }

    public function marginPercent(): float
    {
        $price = (float) $this->price;

        if ($price <= 0) {
            return 0;
        }

        return round((($price - (float) $this->cost_price) / $price) * 100, 2);
    }

    public function isLowStock(): bool
    {
        return $this->isLowStockAt(app(\App\Support\OutletContext::class)->id());
    }

    public function isOutOfStock(): bool
    {
        return $this->isOutOfStockAt(app(\App\Support\OutletContext::class)->id());
    }

    /** Low on the given branch's shelf, or chain-wide when null. */
    public function isLowStockAt(int|Outlet|null $outlet): bool
    {
        if (! $this->track_stock) {
            return false;
        }

        return $this->stockAt($outlet) <= $this->minStockAt($outlet);
    }

    public function isOutOfStockAt(int|Outlet|null $outlet): bool
    {
        return $this->track_stock && $this->stockAt($outlet) <= 0;
    }
}
