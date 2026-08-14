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
            // Being signed in at the till grants nothing here — say so
            // plainly, otherwise an Owner who is already logged into the
            // terminal cannot tell why they are being asked again.
            $atTerminal = auth('pos')->user();

            $message = $atTerminal?->canAccessDashboard()
                ? "Anda sedang masuk sebagai operator kasir ({$atTerminal->name}). "
                    .'Dashboard memakai sesi terpisah — silakan masuk kembali di sini.'
                : 'Silakan masuk untuk mengakses dashboard.';

            return redirect()->route('admin.login')->with('error', $message);
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
