<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOutlet;
use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StockMovement extends Model
{
    use BelongsToOutlet, BelongsToTenant;

    protected $fillable = [
        'tenant_id', 'outlet_id', 'product_id', 'user_id', 'type', 'qty',
        'stock_before', 'stock_after', 'unit_cost',
        'reference_type', 'reference_id', 'note',
    ];

    protected function casts(): array
    {
        return [
            'qty' => 'decimal:3',
            'stock_before' => 'decimal:3',
            'stock_after' => 'decimal:3',
            'unit_cost' => 'decimal:2',
        ];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return array<string,string> */
    public static function types(): array
    {
        return [
            'in' => 'Stok Masuk',
            'out' => 'Stok Keluar',
            'sale' => 'Penjualan',
            'void_return' => 'Pengembalian Void',
            'adjustment' => 'Penyesuaian',
            'opname' => 'Stok Opname',
        ];
    }

    public function typeLabel(): string
    {
        return static::types()[$this->type] ?? ucfirst((string) $this->type);
    }
}
