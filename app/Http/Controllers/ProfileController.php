<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\Sale;
use App\Models\Shift;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * A person's own account, reachable by every role.
 *
 * The same controller serves both doors: Owner and Supervisor open it from
 * the dashboard, a Kasir from the terminal — because a cashier has no
 * dashboard at all and still needs somewhere to change their own PIN.
 *
 * Role and outlet are deliberately read-only here. Those are placements the
 * Owner decides; letting someone edit their own would undo the access model.
 */
class ProfileController extends Controller
{
    /** Stored avatars are square and no larger than this, in pixels. */
    protected const AVATAR_SIZE = 320;

    public function show(): View
    {
        $user = $this->currentUser();

        return view($this->atTerminal() ? 'pos.profile' : 'profile.show', [
            'user' => $user->load('outlet'),
            'stats' => $this->stats($user),
        ]);
    }

    /** Name, username, email, phone — the parts a person owns. */
    public function update(Request $request): RedirectResponse
    {
        $user = $this->currentUser();

        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'username' => [
                'required', 'string', 'max:60', 'alpha_dash',
                Rule::unique('users', 'username')->ignore($user->id),
            ],
            'email' => [
                'required', 'email', 'max:120',
                Rule::unique('users', 'email')->ignore($user->id),
            ],
            'phone' => ['nullable', 'string', 'max:40'],
        ], [], [
            'name' => 'nama', 'username' => 'username',
            'email' => 'email', 'phone' => 'telepon',
        ]);

        $user->update($data);

        ActivityLog::record('profile.update', "Memperbarui profil {$user->name}", $user, [], $user);

        return $this->backToProfile('Profil berhasil diperbarui.');
    }

    public function updateAvatar(Request $request): RedirectResponse
    {
        $request->validate([
            'avatar' => ['required', 'image', 'mimes:jpeg,jpg,png,webp', 'max:4096'],
        ], [
            'avatar.required' => 'Pilih file gambar terlebih dahulu.',
            'avatar.image' => 'File harus berupa gambar.',
            'avatar.mimes' => 'Format yang didukung: JPG, PNG, atau WEBP.',
            'avatar.max' => 'Ukuran gambar maksimal 4 MB.',
        ]);

        $user = $this->currentUser();

        $this->forgetAvatar($user);

        $user->forceFill([
            'avatar_path' => $this->storeAvatar($request->file('avatar'), $user),
        ])->save();

        ActivityLog::record('profile.avatar', "Mengubah foto profil {$user->name}", $user, [], $user);

        return $this->backToProfile('Foto profil diperbarui.');
    }

    public function deleteAvatar(): RedirectResponse
    {
        $user = $this->currentUser();

        $this->forgetAvatar($user);
        $user->forceFill(['avatar_path' => null])->save();

        return $this->backToProfile('Foto profil dihapus.');
    }

    public function updatePassword(Request $request): RedirectResponse
    {
        $user = $this->currentUser();

        $data = $request->validate([
            'current_password' => ['required', 'string'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ], [], [
            'current_password' => 'kata sandi saat ini',
            'password' => 'kata sandi baru',
        ]);

        // Proving the old secret is what stops an unattended session being
        // used to lock the real owner out of their own account.
        if (! Hash::check($data['current_password'], $user->password)) {
            throw ValidationException::withMessages([
                'current_password' => 'Kata sandi saat ini tidak cocok.',
            ]);
        }

        $user->update(['password' => $data['password']]);

        ActivityLog::record('profile.password', "Mengganti kata sandi {$user->name}", $user, [], $user);

        return $this->backToProfile('Kata sandi berhasil diganti.');
    }

    public function updatePin(Request $request): RedirectResponse
    {
        $user = $this->currentUser();

        $data = $request->validate([
            'current_pin' => ['nullable', 'string'],
            'pos_pin' => ['required', 'digits_between:4,8', 'confirmed'],
        ], [
            'pos_pin.confirmed' => 'Ulangi PIN tidak cocok.',
        ], [
            'current_pin' => 'PIN saat ini',
            'pos_pin' => 'PIN baru',
        ]);

        // A cashier only ever knows their PIN, so that is what is checked
        // here. Someone who has never had one can set it without proving
        // anything, because there is nothing yet to prove.
        if ($user->pos_pin && ! Hash::check((string) ($data['current_pin'] ?? ''), $user->pos_pin)) {
            throw ValidationException::withMessages([
                'current_pin' => 'PIN saat ini tidak cocok.',
            ]);
        }

        $user->update(['pos_pin' => $data['pos_pin']]);

        ActivityLog::record('profile.pin', "Mengganti PIN kasir {$user->name}", $user, [], $user);

        return $this->backToProfile('PIN kasir berhasil diganti.');
    }

    // --- Internals --------------------------------------------------------

    protected function currentUser(): User
    {
        return auth('web')->user() ?? auth('pos')->user();
    }

    /** True when the request came from the cashier terminal. */
    protected function atTerminal(): bool
    {
        return ! auth('web')->check() && auth('pos')->check();
    }

    protected function backToProfile(string $message): RedirectResponse
    {
        return redirect()
            ->route($this->atTerminal() ? 'pos.profile' : 'admin.profile')
            ->with('status', $message);
    }

    /**
     * Square-crop and downscale before storing.
     *
     * A phone photo is several megapixels; kept as-is it would be re-sent at
     * full size on every page that shows the little avatar chip.
     */
    protected function storeAvatar(UploadedFile $file, User $user): string
    {
        $source = @imagecreatefromstring((string) file_get_contents($file->getRealPath()));

        if ($source === false) {
            // GD could not read it despite passing validation; keep the
            // original rather than losing the upload entirely.
            return $file->store('avatars', 'public');
        }

        $width = imagesx($source);
        $height = imagesy($source);
        $edge = min($width, $height);

        $canvas = imagecreatetruecolor(self::AVATAR_SIZE, self::AVATAR_SIZE);

        // Flatten onto white: the output is JPEG, which carries no alpha.
        imagefill($canvas, 0, 0, imagecolorallocate($canvas, 255, 255, 255));

        imagecopyresampled(
            $canvas,
            $source,
            0,
            0,
            (int) (($width - $edge) / 2),
            (int) (($height - $edge) / 2),
            self::AVATAR_SIZE,
            self::AVATAR_SIZE,
            $edge,
            $edge,
        );

        ob_start();
        imagejpeg($canvas, null, 88);
        $binary = (string) ob_get_clean();

        imagedestroy($canvas);
        imagedestroy($source);

        $path = 'avatars/'.$user->id.'-'.Str::random(8).'.jpg';

        Storage::disk('public')->put($path, $binary);

        return $path;
    }

    protected function forgetAvatar(User $user): void
    {
        if ($user->avatar_path) {
            Storage::disk('public')->delete($user->avatar_path);
        }
    }

    /**
     * Headline numbers about this operator's own work.
     *
     * Scopes are dropped so the figures cover the person's whole history,
     * not just whichever branch the dashboard happens to be showing.
     *
     * @return array<string,mixed>
     */
    protected function stats(User $user): array
    {
        $sales = fn () => Sale::withoutGlobalScopes()
            ->where('user_id', $user->id)
            ->where('status', 'completed');

        return [
            'sales_count' => $sales()->count(),
            'sales_total' => (float) $sales()->sum('total'),
            'today_count' => $sales()->whereDate('created_at', today())->count(),
            'today_total' => (float) $sales()->whereDate('created_at', today())->sum('total'),
            'shifts_count' => Shift::withoutGlobalScopes()->where('user_id', $user->id)->count(),
            'open_shift' => $user->openShift(),
        ];
    }
}
