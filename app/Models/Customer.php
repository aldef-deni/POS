<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Customer extends Model
{
    use BelongsToTenant, SoftDeletes;

    protected $fillable = [
        'tenant_id', 'code', 'name', 'phone', 'email', 'address',
        'is_member', 'points', 'total_spent', 'visit_count', 'last_visit_at',
    ];

    protected function casts(): array
    {
        return [
            'is_member' => 'boolean',
            'points' => 'integer',
            'visit_count' => 'integer',
            'total_spent' => 'decimal:2',
            'last_visit_at' => 'datetime',
        ];
    }

    public function sales(): HasMany
    {
        return $this->hasMany(Sale::class);
    }

    public function scopeSearch(Builder $query, ?string $term): Builder
    {
        $term = trim((string) $term);

        if ($term === '') {
            return $query;
        }

        return $query->where(function (Builder $q) use ($term) {
            $q->where('name', 'like', "%{$term}%")
                ->orWhere('phone', 'like', "%{$term}%")
                ->orWhere('code', 'like', "%{$term}%");
        });
    }
}
