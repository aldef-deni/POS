<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\User;
use App\Support\Role;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Owner-only management of operators and their roles.
 */
class UserController extends Controller
{
    public function index(): View
    {
        return view('dashboard.users.index', [
            'users' => User::orderByRaw("FIELD(role, 'Owner', 'Supervisor', 'Kasir')")
                ->orderBy('name')
                ->get(),
            'roles' => Role::all(),
        ]);
    }

    public function create(): View
    {
        return view('dashboard.users.form', [
            'user' => new User(['role' => Role::Kasir, 'is_active' => true]),
            'roles' => Role::all(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'username' => ['required', 'string', 'max:60', 'alpha_dash', Rule::unique('users', 'username')],
            'email' => ['required', 'email', 'max:120', Rule::unique('users', 'email')],
            'role' => ['required', Rule::enum(Role::class)],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
            'pos_pin' => ['nullable', 'digits_between:4,8'],
            'phone' => ['nullable', 'string', 'max:40'],
        ], [], [
            'name' => 'nama', 'username' => 'username', 'email' => 'email',
            'password' => 'kata sandi', 'pos_pin' => 'PIN kasir',
        ]);

        $data['is_active'] = $request->boolean('is_active', true);

        $user = User::create($data);

        ActivityLog::record(
            'user.create',
            "Menambah pengguna {$user->name} sebagai {$user->role->value}",
            $user,
        );

        return redirect()->route('admin.users.index')
            ->with('status', "Pengguna {$user->name} dibuat sebagai {$user->role->value}.");
    }

    public function edit(User $user): View
    {
        return view('dashboard.users.form', [
            'user' => $user,
            'roles' => Role::all(),
        ]);
    }

    public function update(Request $request, User $user): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'username' => ['required', 'string', 'max:60', 'alpha_dash', Rule::unique('users', 'username')->ignore($user->id)],
            'email' => ['required', 'email', 'max:120', Rule::unique('users', 'email')->ignore($user->id)],
            'role' => ['required', Rule::enum(Role::class)],
            'password' => ['nullable', 'string', 'min:8', 'confirmed'],
            'pos_pin' => ['nullable', 'digits_between:4,8'],
            'phone' => ['nullable', 'string', 'max:40'],
        ]);

        // Blank fields mean "leave the existing secret alone".
        foreach (['password', 'pos_pin'] as $secret) {
            if (blank($data[$secret] ?? null)) {
                unset($data[$secret]);
            }
        }

        $data['is_active'] = $request->boolean('is_active');

        // Never let an Owner lock themselves out of their own account.
        if ($user->id === $request->user()->id) {
            $data['role'] = $user->role->value;
            $data['is_active'] = true;
        }

        $user->update($data);

        ActivityLog::record('user.update', "Mengubah pengguna {$user->name}", $user);

        return redirect()->route('admin.users.index')->with('status', 'Data pengguna diperbarui.');
    }

    public function destroy(Request $request, User $user): RedirectResponse
    {
        if ($user->id === $request->user()->id) {
            return back()->with('error', 'Anda tidak dapat menghapus akun sendiri.');
        }

        if ($user->isOwner() && User::where('role', Role::Owner->value)->count() <= 1) {
            return back()->with('error', 'Minimal harus ada satu Owner yang aktif.');
        }

        if ($user->sales()->exists()) {
            // Sales history must keep pointing at a real operator, so the
            // account is deactivated instead of deleted.
            $user->update(['is_active' => false]);

            ActivityLog::record('user.deactivate', "Menonaktifkan pengguna {$user->name}", $user);

            return back()->with('status', "Pengguna {$user->name} dinonaktifkan karena sudah memiliki riwayat transaksi.");
        }

        $name = $user->name;
        $user->delete();

        ActivityLog::record('user.delete', "Menghapus pengguna {$name}");

        return back()->with('status', "Pengguna {$name} dihapus.");
    }
}
