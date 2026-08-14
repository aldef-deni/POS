<?php

namespace App\Models\Concerns;

use App\Models\Tenant;
use App\Support\Tenancy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Scopes a model to the tenant resolved for the current request and stamps
 * `tenant_id` on create, so no controller ever has to remember to do it.
 */
trait BelongsToTenant
{
    public static function bootBelongsToTenant(): void
    {
        static::addGlobalScope('tenant', function (Builder $builder) {
            $tenancy = app(Tenancy::class);

            if ($tenancy->has()) {
                $builder->where($builder->getModel()->getTable().'.tenant_id', $tenancy->id());
            }
        });

        static::creating(function ($model) {
            if ($model->tenant_id === null) {
                $model->tenant_id = app(Tenancy::class)->id();
            }
        });
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /** Escape hatch for cross-tenant queries (admin tooling, reports). */
    public function scopeWithoutTenantScope(Builder $query): Builder
    {
        return $query->withoutGlobalScope('tenant');
    }
}
