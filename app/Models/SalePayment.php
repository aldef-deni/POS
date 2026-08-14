<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SalePayment extends Model
{
    protected $fillable = [
        'sale_id', 'method', 'amount', 'reference', 'provider',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
        ];
    }

    public function sale(): BelongsTo
    {
        return $this->belongsTo(Sale::class);
    }

    /** @return array<string,string> */
    public static function methods(): array
    {
        return [
            'cash' => 'Tunai',
            'qris' => 'QRIS',
            'card' => 'Kartu Debit/Kredit',
            'transfer' => 'Transfer Bank',
            'ewallet' => 'E-Wallet',
            'voucher' => 'Voucher',
        ];
    }

    public function methodLabel(): string
    {
        return static::methods()[$this->method] ?? ucfirst((string) $this->method);
    }
}
