<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\User;
use App\Services\DemoResetService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * Sign-in for the management dashboard, on the `web` guard.
 */
class AdminAuthController extends Controller
{
    public function show(): View
    {
        // Kredensial demo dikirim ke tampilan supaya tombolnya bisa mengisi
        // kolom. Aman ditampilkan: akun ini memang untuk dicoba siapa saja,
        // dan isinya dibangun ulang berkala.
        return view('auth.admin-login', [
            'demoLogin' => config('demo.username') ?: null,
            'demoPassword' => config('demo.password'),
        ]);
    }

    public function login(Request $request, DemoResetService $demo): RedirectResponse
    {
        // Pemulihan demo dijalankan SEBELUM kredensial diperiksa. Kalau
        // menunggu login berhasil, pengunjung yang mengganti kata sandi akun
        // demo akan mengunci semua orang - pemulihannya tidak akan pernah
        // terpicu lagi.
        if ($demo->cocokDenganDemo($request->input('login'))) {
            $demo->pulihkanBilaPerlu();
        }

        $credentials = $request->validate([
            'login' => ['required', 'string', 'max:120'],
            'password' => ['required', 'string'],
        ], [], [
            'login' => 'email atau username',
        ]);

        $field = filter_var($credentials['login'], FILTER_VALIDATE_EMAIL) ? 'email' : 'username';

        $user = User::withoutGlobalScope('tenant')
            ->where($field, $credentials['login'])
            ->first();

        if (! $user || ! Auth::guard('web')->getProvider()->validateCredentials($user, [
            'password' => $credentials['password'],
        ])) {
            throw ValidationException::withMessages([
                'login' => 'Email/username atau kata sandi tidak cocok.',
            ]);
        }

        if (! $user->is_active) {
            throw ValidationException::withMessages([
                'login' => 'Akun Anda dinonaktifkan. Hubungi Owner.',
            ]);
        }

        // A cashier has no back office at all — send them to the terminal
        // instead of leaving them at a dead end.
        if (! $user->canAccessDashboard()) {
            throw ValidationException::withMessages([
                'login' => 'Peran Kasir tidak memiliki akses dashboard. Silakan gunakan terminal kasir di /pos.',
            ]);
        }

        Auth::guard('web')->login($user, $request->boolean('remember'));
        $request->session()->regenerate();

        $user->forceFill(['last_login_at' => now()])->save();

        ActivityLog::record('auth.login', 'Masuk ke dashboard', $user, [], $user, 'web');

        return redirect()->intended(route('admin.dashboard'));
    }

    public function logout(Request $request): RedirectResponse
    {
        if ($user = Auth::guard('web')->user()) {
            ActivityLog::record('auth.logout', 'Keluar dari dashboard', $user, [], $user, 'web');
        }

        Auth::guard('web')->logout();

        // Only tear the session down when no terminal session is riding
        // along in the same browser.
        if (! Auth::guard('pos')->check()) {
            $request->session()->invalidate();
            $request->session()->regenerateToken();
        }

        return redirect()->route('admin.login')->with('status', 'Anda telah keluar.');
    }
}
