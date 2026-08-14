<?php

namespace App\Http\Middleware;

use App\Models\Tenant;
use App\Support\Tenancy;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\View;
use Symfony\Component\HttpFoundation\Response;

/**
 * Works out which business this request belongs to and shares it with the
 * container and every view.
 *
 * An authenticated operator carries their tenant on the user row. Anonymous
 * visitors (the terminal sign-in screen, the landing page) fall back to the
 * first active tenant so branding still renders.
 */
class ResolveTenant
{
    public function handle(Request $request, Closure $next): Response
    {
        $tenancy = app(Tenancy::class);

        // The dashboard and the terminal use different guards; either one
        // tells us the tenant.
        $user = auth('web')->user() ?? auth('pos')->user();

        $tenant = $user?->tenant_id
            ? Tenant::find($user->tenant_id)
            : Tenant::where('is_active', true)->orderBy('id')->first();

        $tenancy->set($tenant);

        View::share('tenant', $tenant);

        return $next($request);
    }
}
