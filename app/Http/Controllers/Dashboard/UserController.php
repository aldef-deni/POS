<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\Outlet;
use App\Models\User;
use App\Support\Role;
use App\Support\Tenancy;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * Owner-only management of operators and their roles.
 */
class UserController extends Controller
{
    public function index(): View
    {
        return view('dashboard.users.index', [
            'users' => User::with('outlet')
                ->orderByRaw("FIELD(role, 'Owner', 'Supervisor', 'Kasir')")
                ->orderBy('name')
                ->get(),
            'roles' => Role::all(),
            'outlets' => Outlet::orderBy('sort_order')->orderBy('name')->get(),
        ]);
    }

    public function create(): View
    {
        return view('dashboard.users.form', [
            'user' => new User(['role' => Role::Kasir, 'is_active' => true]),
            'roles' => Role::all(),
            'outlets' => Outlet::active()->orderBy('sort_order')->orderBy('name')->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'username' => ['required', 'string', 'max:60', 'alpha_dash', Rule::unique('users', 'username')],
            'email' => ['required', 'email', 'max:120', Rule::unique('users', 'email')],
            'role' => ['required', Rule::enum(Role::class)],
            'outlet_id' => ['required', 'string'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
            'pos_pin' => ['nullable', 'digits_between:4,8'],
            'phone' => ['nullable', 'string', 'max:40'],
        ], [
            'outlet_id.required' => 'Outlet penempatan wajib dipilih.',
        ], [
            'name' => 'nama', 'username' => 'username', 'email' => 'email',
            'password' => 'kata sandi', 'pos_pin' => 'PIN kasir', 'outlet_id' => 'outlet',
        ]);

        $data['outlet_id'] = $this->resolveOutlet($data['outlet_id'], $data['role']);
        $data['is_active'] = $request->boolean('is_active', true);

        $user = User::create($data);

        $where = $user->outlet?->name ?? 'semua outlet';

        ActivityLog::record(
            'user.create',
            "Menambah pengguna {$user->name} sebagai {$user->role->value} di {$where}",
            $user,
        );

        return redirect()->route('admin.users.index')
            ->with('status', "Pengguna {$user->name} dibuat sebagai {$user->role->value} di {$where}.");
    }

    public function edit(User $user): View
    {
        return view('dashboard.users.form', [
            'user' => $user,
            'roles' => Role::all(),
            'outlets' => Outlet::active()->orderBy('sort_order')->orderBy('name')->get(),
        ]);
    }

    public function update(Request $request, User $user): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'username' => ['required', 'string', 'max:60', 'alpha_dash', Rule::unique('users', 'username')->ignore($user->id)],
            'email' => ['required', 'email', 'max:120', Rule::unique('users', 'email')->ignore($user->id)],
            // Editing your own account leaves the role controls disabled, so
            // the field is only demanded when changing somebody else. Either
            // way the value is forced back below for self-edits.
            'role' => [Rule::requiredIf($user->id !== $request->user()->id), Rule::enum(Role::class)],
            'outlet_id' => ['required', 'string'],
            'password' => ['nullable', 'string', 'min:8', 'confirmed'],
            'pos_pin' => ['nullable', 'digits_between:4,8'],
            'phone' => ['nullable', 'string', 'max:40'],
        ], [
            'outlet_id.required' => 'Outlet penempatan wajib dipilih.',
            'role.required' => 'Peran wajib dipilih.',
        ], ['outlet_id' => 'outlet', 'role' => 'peran']);

        // Blank fields mean "leave the existing secret alone".
        foreach (['password', 'pos_pin'] as $secret) {
            if (blank($data[$secret] ?? null)) {
                unset($data[$secret]);
            }
        }

        $data['outlet_id'] = $this->resolveOutlet(
            $data['outlet_id'],
            $data['role'] ?? $user->role->value,
        );
        $data['is_active'] = $request->boolean('is_active');

        // Never let an Owner lock themselves out of their own account.
        if ($user->id === $request->user()->id) {
            $data['role'] = $user->role->value;
            $data['is_active'] = true;
        }

        // Moving an operator mid-shift would split their takings across two
        // branches, so the drawer has to be closed first.
        if ((int) $data['outlet_id'] !== (int) $user->outlet_id && $user->openShift()) {
            return back()->withInput()->with('error',
                "{$user->name} masih memiliki shift terbuka. Tutup shift tersebut sebelum memindahkan outlet.");
        }

        $user->update($data);

        ActivityLog::record('user.update', "Mengubah pengguna {$user->name}", $user);

        return redirect()->route('admin.users.index')->with('status', 'Data pengguna diperbarui.');
    }

    /**
     * Turn the submitted outlet choice into an id.
     *
     * The field is always required — an operator must be placed deliberately,
     * not by leaving a dropdown on its first entry. Only an Owner may take
     * the explicit "all outlets" option; a Supervisor or Kasir without a
     * branch could otherwise ring sales up against the wrong one.
     */
    protected function resolveOutlet(string $choice, string $role): ?int
    {
        if ($choice === 'all') {
            if ($role !== Role::Owner->value) {
                throw ValidationException::withMessages([
                    'outlet_id' => 'Hanya Owner yang boleh ditugaskan ke semua outlet. '
                        .'Pilih satu outlet untuk peran '.$role.'.',
                ]);
            }

            return null;
        }

        $outlet = Outlet::where('tenant_id', app(Tenancy::class)->id())
            ->whereKey($choice)
            ->first();

        if (! $outlet) {
            throw ValidationException::withMessages([
                'outlet_id' => 'Outlet yang dipilih tidak ditemukan.',
            ]);
        }

        if (! $outlet->is_active) {
            throw ValidationException::withMessages([
                'outlet_id' => "Outlet {$outlet->name} sedang nonaktif dan tidak dapat menerima penempatan.",
            ]);
        }

        return $outlet->id;
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
