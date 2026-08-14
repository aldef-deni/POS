<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SaleItem extends Model
{
    protected $fillable = [
        'sale_id', 'product_id', 'sku', 'name', 'unit',
        'qty', 'unit_price', 'cost_price', 'discount_amount',
        'tax_amount', 'line_total', 'note',
    ];

    protected function casts(): array
    {
        return [
            'qty' => 'decimal:3',
            'unit_price' => 'decimal:2',
            'cost_price' => 'decimal:2',
            'discount_amount' => 'decimal:2',
            'tax_amount' => 'decimal:2',
            'line_total' => 'decimal:2',
        ];
    }

    public function sale(): BelongsTo
    {
        return $this->belongsTo(Sale::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function profit(): float
    {
        return (float) $this->line_total - ((float) $this->cost_price * (float) $this->qty);
    }
}
