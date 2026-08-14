<?php

namespace App\Http\Middleware;

use App\Models\Outlet;
use App\Models\Tenant;
use App\Models\User;
use App\Support\OutletContext;
use App\Support\Tenancy;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\View;
use Symfony\Component\HttpFoundation\Response;

/**
 * Works out which business — and which branch of it — this request belongs
 * to, then shares both with the container and every view.
 */
class ResolveTenant
{
    /** Session key holding an Owner's chosen branch on the dashboard. */
    public const OUTLET_SESSION_KEY = 'dashboard.outlet_id';

    public function handle(Request $request, Closure $next): Response
    {
        // The dashboard and the terminal use different guards; either one
        // tells us the tenant.
        $user = auth('web')->user() ?? auth('pos')->user();

        $tenant = $user?->tenant_id
            ? Tenant::find($user->tenant_id)
            : Tenant::where('is_active', true)->orderBy('id')->first();

        app(Tenancy::class)->set($tenant);

        $outlet = $this->resolveOutlet($request, $user);
        app(OutletContext::class)->set($outlet);

        View::share('tenant', $tenant);
        View::share('outlet', $outlet);
        View::share('outletOptions', $tenant ? Outlet::active()->orderBy('sort_order')->orderBy('name')->get() : collect());

        return $next($request);
    }

    /**
     * An operator assigned to a branch is pinned to it — that assignment is
     * the safeguard against ringing a sale up at the wrong outlet. Only an
     * unassigned Owner may roam, and only on the dashboard.
     */
    protected function resolveOutlet(Request $request, ?User $user): ?Outlet
    {
        if (! $user) {
            return null;
        }

        if ($user->outlet_id) {
            return Outlet::find($user->outlet_id);
        }

        // A cashier without a branch cannot sell at all; AuthenticatePos
        // turns that into a clear message rather than a silent free-for-all.
        if (! auth('web')->check()) {
            return null;
        }

        $chosen = $request->session()->get(self::OUTLET_SESSION_KEY);

        return $chosen ? Outlet::find($chosen) : null;
    }
}
