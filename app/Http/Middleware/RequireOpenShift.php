<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Selling requires an open cash-drawer session, so every transaction can be
 * reconciled against a shift at close.
 */
class RequireOpenShift
{
    public function handle(Request $request, Closure $next): Response
    {
        $shift = auth('pos')->user()?->openShift();

        if (! $shift) {
            if ($request->expectsJson()) {
                return response()->json([
                    'message' => 'Belum ada shift yang dibuka.',
                    'redirect' => route('pos.shift.open'),
                ], 409);
            }

            return redirect()->route('pos.shift.open')
                ->with('error', 'Buka shift terlebih dahulu sebelum melakukan transaksi.');
        }

        $request->attributes->set('shift', $shift);

        return $next($request);
    }
}
