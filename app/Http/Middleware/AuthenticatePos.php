<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Guards the cashier terminal.
 *
 * The terminal has its own sign-in step on the `pos` guard, so an operator
 * can work a till without ever holding a dashboard session.
 */
class AuthenticatePos
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! auth('pos')->check()) {
            if ($request->expectsJson()) {
                return response()->json([
                    'message' => 'Sesi kasir berakhir. Silakan masuk kembali.',
                    'redirect' => route('pos.login'),
                ], 401);
            }

            return redirect()->route('pos.login')
                ->with('error', 'Silakan masuk terlebih dahulu untuk menggunakan kasir.');
        }

        $user = auth('pos')->user();

        if (! $user->is_active || ! $user->hasPermission('pos.access')) {
            auth('pos')->logout();

            return redirect()->route('pos.login')
                ->with('error', 'Akun ini tidak diizinkan mengoperasikan kasir.');
        }

        return $next($request);
    }
}
