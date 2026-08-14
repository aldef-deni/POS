<?php

namespace App\Models\Concerns;

use App\Models\Outlet;
use App\Support\OutletContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Scopes a model to the branch resolved for the current request and stamps
 * `outlet_id` on create.
 *
 * When no outlet is resolved the scope stays off, which is how an Owner sees
 * every branch at once on the dashboard.
 */
trait BelongsToOutlet
{
    public static function bootBelongsToOutlet(): void
    {
        static::addGlobalScope('outlet', function (Builder $builder) {
            $context = app(OutletContext::class);

            if ($context->has()) {
                $builder->where($builder->getModel()->getTable().'.outlet_id', $context->id());
            }
        });

        static::creating(function ($model) {
            if ($model->outlet_id === null) {
                $model->outlet_id = app(OutletContext::class)->id();
            }
        });
    }

    public function outlet(): BelongsTo
    {
        return $this->belongsTo(Outlet::class);
    }

    /** Read across every branch, regardless of the active context. */
    public function scopeWithoutOutletScope(Builder $query): Builder
    {
        return $query->withoutGlobalScope('outlet');
    }

    /** Restrict to one branch explicitly, ignoring the ambient context. */
    public function scopeForOutlet(Builder $query, int|string|null $outletId): Builder
    {
        $query = $query->withoutGlobalScope('outlet');

        return $outletId
            ? $query->where($query->getModel()->getTable().'.outlet_id', $outletId)
            : $query;
    }
}
