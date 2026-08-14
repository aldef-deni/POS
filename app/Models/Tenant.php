<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Tenant extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'tax_enabled' => 'boolean',
            'tax_inclusive' => 'boolean',
            'tax_percent' => 'decimal:2',
            'service_charge_percent' => 'decimal:2',
            'receipt_show_qr' => 'boolean',
            'receipt_show_logo' => 'boolean',
            'sku_include_category' => 'boolean',
            'sku_sequence_length' => 'integer',
            'sku_next_number' => 'integer',
            'low_stock_threshold' => 'integer',
            'allow_negative_stock' => 'boolean',
            'is_active' => 'boolean',
            'plan_expires_at' => 'date',
        ];
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }

    public function categories(): HasMany
    {
        return $this->hasMany(Category::class);
    }

    public function sales(): HasMany
    {
        return $this->hasMany(Sale::class);
    }

    /** Absolute URL to the uploaded logo, or null when none is set. */
    public function logoUrl(): ?string
    {
        return $this->logo_path
            ? \Illuminate\Support\Facades\Storage::disk('public')->url($this->logo_path)
            : null;
    }

    /**
     * Apply the configured cash-rounding rule to a grand total. Indonesian
     * retail commonly rounds to the nearest 100 or 500 rupiah.
     */
    public function roundTotal(float $amount): float
    {
        $step = match ($this->rounding_mode) {
            'nearest_100' => 100,
            'nearest_500' => 500,
            'nearest_1000' => 1000,
            default => 0,
        };

        if ($step === 0) {
            return round($amount, 2);
        }

        return (float) (round($amount / $step) * $step);
    }
}
