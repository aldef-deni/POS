<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Category extends Model
{
    use BelongsToTenant, SoftDeletes;

    protected $fillable = [
        'tenant_id', 'name', 'code', 'color', 'sort_order', 'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /**
     * Short code injected into generated product IDs. Falls back to the
     * first three letters of the name when the Owner left it blank.
     */
    public function skuCode(): string
    {
        if (filled($this->code)) {
            return strtoupper($this->code);
        }

        return strtoupper(substr(preg_replace('/[^A-Za-z0-9]/', '', (string) $this->name) ?: 'GEN', 0, 3));
    }
}
