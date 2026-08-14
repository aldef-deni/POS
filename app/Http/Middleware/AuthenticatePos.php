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

        // Selling without a branch would leave the sale and its stock
        // movement unattributed, so the terminal refuses to open.
        if (! $user->outlet_id) {
            auth('pos')->logout();

            return redirect()->route('pos.login')->with(
                'error',
                "Akun {$user->name} belum ditempatkan pada outlet mana pun. "
                    .'Minta Owner menetapkan outlet melalui Dashboard → Pengguna & Peran.'
            );
        }

        return $next($request);
    }
}
