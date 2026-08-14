<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Guards the management dashboard.
 *
 * Signing in at the cashier terminal grants nothing here: a Kasir who reaches
 * a dashboard URL is sent straight back to the terminal, which is what keeps
 * operators out of the back office.
 */
class AuthenticateDashboard
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! auth('web')->check()) {
            return redirect()->route('admin.login')
                ->with('error', 'Silakan masuk untuk mengakses dashboard.');
        }

        $user = auth('web')->user();

        if (! $user->is_active) {
            auth('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('admin.login')
                ->with('error', 'Akun Anda dinonaktifkan. Hubungi Owner.');
        }

        if (! $user->canAccessDashboard()) {
            return redirect()->route('pos.index')
                ->with('error', 'Peran Kasir tidak memiliki akses ke dashboard pengelola.');
        }

        return $next($request);
    }
}
