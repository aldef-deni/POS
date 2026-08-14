<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Route guard for whole-role restrictions, e.g. `role:Owner`.
 */
class EnsureRole
{
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = auth('web')->user() ?? auth('pos')->user();

        if (! $user || ! in_array($user->role?->value, $roles, true)) {
            abort(403, 'Peran Anda tidak memiliki akses ke halaman ini.');
        }

        return $next($request);
    }
}
