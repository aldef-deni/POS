<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Outlet extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'tenant_id', 'name', 'code', 'address', 'city', 'phone',
        'receipt_footer', 'is_active', 'is_default', 'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'is_default' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function sales(): HasMany
    {
        return $this->hasMany(Sale::class);
    }

    public function shifts(): HasMany
    {
        return $this->hasMany(Shift::class);
    }

    public function stocks(): HasMany
    {
        return $this->hasMany(OutletStock::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /** The branch new operators and stock fall back to. */
    public static function default(): ?self
    {
        return static::where('is_default', true)->first()
            ?? static::active()->orderBy('sort_order')->orderBy('id')->first();
    }

    /** Address for the receipt: the branch's own, else the tenant's. */
    public function printableAddress(): ?string
    {
        return filled($this->address) ? $this->address : $this->tenant?->address;
    }

    public function printablePhone(): ?string
    {
        return filled($this->phone) ? $this->phone : $this->tenant?->phone;
    }

    public function printableFooter(): ?string
    {
        return filled($this->receipt_footer) ? $this->receipt_footer : $this->tenant?->receipt_footer;
    }

    /** Number of staff assigned here, used on the outlets screen. */
    public function operatorCount(): int
    {
        return $this->users()->where('is_active', true)->count();
    }
}
