<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class RedirectIfKasir
{
    public function handle(Request $request, Closure $next)
    {
        $user = Auth::user();
        if ($user && isset($user->role) && $user->role === 'Kasir') {
            abort(403, 'Unauthorized access.');
        }

        return $next($request);
    }
}
