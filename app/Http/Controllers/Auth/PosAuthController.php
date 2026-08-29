<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\User;
use App\Services\DemoResetService;
use App\Support\Role;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * Sign-in for the cashier terminal, on the dedicated `pos` guard.
 *
 * Entry is by operator + PIN only. A keypad is quicker than a keyboard at a
 * counter, it makes hand-over between shifts fast, and it keeps dashboard
 * passwords off the shop floor. The resulting session grants the terminal
 * only, never the dashboard.
 */
class PosAuthController extends Controller
{
    public function show(DemoResetService $demo): View
    {
        // The till is staffed by cashiers, so only they are offered here -
        // and only once a PIN has been set for them.
        $operators = User::where('is_active', true)
            ->where('role', Role::Kasir->value)
            ->whereNotNull('pos_pin')
            ->orderBy('name')
            ->get(['id', 'tenant_id', 'name', 'username', 'role', 'avatar_path']);

        // PIN kasir demo diisikan sendiri saat operatornya dipilih. Pengunjung
        // tidak punya cara menebaknya, dan menuliskannya di teks bantuan sama
        // terbukanya dengan menaruhnya di sini - hanya lebih merepotkan.
        //
        // Hanya operator milik tenant demo yang menghasilkan nilai; PIN kasir
        // pelanggan disimpan ter-hash sehingga memang tidak ada yang bisa
        // dibocorkan lewat jalan ini.
        $demoPins = $operators
            ->mapWithKeys(fn (User $operator) => [$operator->id => $demo->pinKasir($operator)])
            ->filter()
            ->all();

        return view('pos.login', compact('operators', 'demoPins'));
    }

    public function login(Request $request): RedirectResponse
    {
        // PIN is the only way in at the terminal: it is faster at a counter
        // and keeps dashboard passwords off the shop floor.
        $user = $this->resolveByPin($request);

        if (! $user->is_active || ! $user->hasPermission('pos.access')) {
            throw ValidationException::withMessages([
                'login' => 'Akun ini tidak diizinkan mengoperasikan kasir.',
            ]);
        }

        Auth::guard('pos')->login($user, true);
        $request->session()->regenerate();

        $user->forceFill(['last_pos_login_at' => now()])->save();

        ActivityLog::record('pos.login', 'Masuk ke terminal kasir', $user, [], $user, 'pos');

        // Straight to the till if a drawer is already open, otherwise the
        // operator declares their opening float first.
        return redirect()->route($user->openShift() ? 'pos.index' : 'pos.shift.open');
    }

    protected function resolveByPin(Request $request): User
    {
        $data = $request->validate([
            'user_id' => ['required', 'integer'],
            'pin' => ['required', 'string', 'min:4', 'max:8'],
        ], [], [
            'user_id' => 'operator',
            'pin' => 'PIN',
        ]);

        $user = User::withoutGlobalScope('tenant')->find($data['user_id']);

        if (! $user || ! $user->pos_pin || ! Hash::check($data['pin'], $user->pos_pin)) {
            throw ValidationException::withMessages([
                'pin' => 'PIN tidak cocok.',
            ]);
        }

        return $user;
    }

    public function logout(Request $request): RedirectResponse
    {
        if ($user = Auth::guard('pos')->user()) {
            ActivityLog::record('pos.logout', 'Keluar dari terminal kasir', $user, [], $user, 'pos');
        }

        Auth::guard('pos')->logout();

        if (! Auth::guard('web')->check()) {
            $request->session()->invalidate();
            $request->session()->regenerateToken();
        }

        return redirect()->route('pos.login')->with('status', 'Sesi kasir diakhiri.');
    }
}
