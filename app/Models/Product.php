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

    public function saleItems(): HasMany
    {
        return $this->hasMany(SaleItem::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /** Products at or below their reorder point. */
    public function scopeLowStock(Builder $query): Builder
    {
        return $query->where('track_stock', true)
            ->whereColumn('stock', '<=', 'min_stock');
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
        return $this->track_stock && (float) $this->stock <= (float) $this->min_stock;
    }

    public function isOutOfStock(): bool
    {
        return $this->track_stock && (float) $this->stock <= 0;
    }
}
