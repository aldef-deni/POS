<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Fine-grained route guard, e.g. `can.do:settings.manage`. Passing several
 * permissions requires all of them.
 */
class EnsurePermission
{
    public function handle(Request $request, Closure $next, string ...$permissions): Response
    {
        $user = auth('web')->user() ?? auth('pos')->user();

        foreach ($permissions as $permission) {
            if (! $user?->hasPermission($permission)) {
                abort(403, 'Anda tidak memiliki izin untuk tindakan ini.');
            }
        }

        return $next($request);
    }
}
