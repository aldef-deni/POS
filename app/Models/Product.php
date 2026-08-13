<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Product extends Model
{
    use HasFactory;

    protected $fillable = [
        'name', 'sku', 'barcode_value', 'qr_value', 'price', 'stock'
    ];

    protected static function booted()
    {
        static::creating(function ($product) {
            if (empty($product->sku)) {
                $product->sku = 'PRD'.time().rand(100,999);
            }

            if (empty($product->barcode_value)) {
                $product->barcode_value = $product->sku;
            }

            if (empty($product->qr_value)) {
                $product->qr_value = $product->sku;
            }
        });
    }
}
