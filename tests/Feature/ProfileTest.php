<?php

namespace Tests\Feature;

use App\Support\Role;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Tests\PosTestCase;

/**
 * Everyone's own account page — reachable from the dashboard and, for a
 * cashier who has no dashboard at all, from the terminal.
 */
class ProfileTest extends PosTestCase
{
    // --- Reachability -----------------------------------------------------

    public function test_dashboard_users_can_open_their_profile(): void
    {
        foreach ([$this->owner, $this->supervisor] as $user) {
            $this->actingAs($user, 'web')
                ->get('/dashboard/profile')
                ->assertOk()
                ->assertSee($user->name)
                ->assertSee('Profil Saya');
        }
    }

    public function test_cashier_can_open_their_profile_from_the_terminal(): void
    {
        // A Kasir has no dashboard, so the terminal is their only way in.
        $this->actingAs($this->kasir, 'pos')
            ->get('/pos/profile')
            ->assertOk()
            ->assertSee($this->kasir->name)
            ->assertSee('Ganti PIN Kasir');
    }

    public function test_cashier_reaches_their_profile_without_an_open_shift(): void
    {
        // Sits outside the shift guard on purpose: a cashier may need to fix
        // their PIN before they can open a drawer at all.
        $this->actingAs($this->kasir, 'pos');

        $this->assertNull($this->kasir->openShift());
        $this->get('/pos/profile')->assertOk();
    }

    public function test_profile_requires_a_signed_in_operator(): void
    {
        $this->get('/dashboard/profile')->assertRedirect(route('admin.login'));
        $this->get('/pos/profile')->assertRedirect(route('pos.login'));
    }

    // --- Editing your own details ----------------------------------------

    public function test_operator_can_edit_their_own_details(): void
    {
        $this->actingAs($this->kasir, 'pos');

        $this->put('/pos/profile', [
            'name' => 'Budi Baru',
            'username' => 'budibaru',
            'email' => 'budibaru@uji.test',
            'phone' => '081298765432',
        ])->assertRedirect(route('pos.profile'));

        $this->kasir->refresh();

        $this->assertSame('Budi Baru', $this->kasir->name);
        $this->assertSame('budibaru', $this->kasir->username);
        $this->assertSame('081298765432', $this->kasir->phone);
    }

    public function test_username_and_email_stay_unique(): void
    {
        $this->actingAs($this->kasir, 'pos');

        $this->put('/pos/profile', [
            'name' => $this->kasir->name,
            'username' => $this->supervisor->username,
            'email' => $this->supervisor->email,
        ])->assertSessionHasErrors(['username', 'email']);
    }

    public function test_profile_cannot_change_your_own_role_or_outlet(): void
    {
        $this->actingAs($this->kasir, 'pos');

        // Those are the Owner's decisions; smuggling them in must do nothing.
        $this->put('/pos/profile', [
            'name' => $this->kasir->name,
            'username' => $this->kasir->username,
            'email' => $this->kasir->email,
            'role' => Role::Owner->value,
            'outlet_id' => $this->outletB->id,
            'is_active' => '0',
        ])->assertRedirect();

        $this->kasir->refresh();

        $this->assertSame(Role::Kasir, $this->kasir->role);
        $this->assertSame($this->outletA->id, $this->kasir->outlet_id);
        $this->assertTrue($this->kasir->is_active);
    }

    // --- Password ---------------------------------------------------------

    public function test_password_change_requires_the_current_one(): void
    {
        $this->actingAs($this->supervisor, 'web');

        $this->put('/dashboard/profile/password', [
            'current_password' => 'salah-sekali',
            'password' => 'sandibaru123',
            'password_confirmation' => 'sandibaru123',
        ])->assertSessionHasErrors('current_password');

        $this->assertTrue(Hash::check('rahasia123', $this->supervisor->fresh()->password));
    }

    public function test_password_changes_with_the_correct_current_one(): void
    {
        $this->actingAs($this->supervisor, 'web');

        $this->put('/dashboard/profile/password', [
            'current_password' => 'rahasia123',
            'password' => 'sandibaru123',
            'password_confirmation' => 'sandibaru123',
        ])->assertRedirect(route('admin.profile'));

        $this->assertTrue(Hash::check('sandibaru123', $this->supervisor->fresh()->password));
    }

    public function test_password_confirmation_must_match(): void
    {
        $this->actingAs($this->supervisor, 'web');

        $this->put('/dashboard/profile/password', [
            'current_password' => 'rahasia123',
            'password' => 'sandibaru123',
            'password_confirmation' => 'beda-sekali',
        ])->assertSessionHasErrors('password');
    }

    // --- PIN --------------------------------------------------------------

    public function test_pin_change_requires_the_current_pin(): void
    {
        // A cashier only knows their PIN, so that is the secret checked.
        $this->actingAs($this->kasir, 'pos');

        $this->put('/pos/profile/pin', [
            'current_pin' => '0000',
            'pos_pin' => '5678',
            'pos_pin_confirmation' => '5678',
        ])->assertSessionHasErrors('current_pin');

        $this->assertTrue(Hash::check('1234', $this->kasir->fresh()->pos_pin));
    }

    public function test_pin_changes_with_the_correct_current_pin(): void
    {
        $this->actingAs($this->kasir, 'pos');

        $this->put('/pos/profile/pin', [
            'current_pin' => '1234',
            'pos_pin' => '567890',
            'pos_pin_confirmation' => '567890',
        ])->assertRedirect(route('pos.profile'));

        $this->assertTrue(Hash::check('567890', $this->kasir->fresh()->pos_pin));
    }

    public function test_an_operator_without_a_pin_can_set_one(): void
    {
        $this->kasir->forceFill(['pos_pin' => null])->save();

        $this->actingAs($this->kasir, 'pos');

        // Nothing to prove when there is no PIN yet.
        $this->put('/pos/profile/pin', [
            'pos_pin' => '4321',
            'pos_pin_confirmation' => '4321',
        ])->assertRedirect();

        $this->assertTrue(Hash::check('4321', $this->kasir->fresh()->pos_pin));
    }

    public function test_pin_must_be_four_to_eight_digits(): void
    {
        $this->actingAs($this->kasir, 'pos');

        foreach (['123', '123456789', 'abcd'] as $bad) {
            $this->put('/pos/profile/pin', [
                'current_pin' => '1234',
                'pos_pin' => $bad,
                'pos_pin_confirmation' => $bad,
            ])->assertSessionHasErrors('pos_pin');
        }
    }

    // --- Avatar -----------------------------------------------------------

    public function test_avatar_is_squared_and_downscaled_on_upload(): void
    {
        $this->actingAs($this->kasir, 'pos');

        $this->post('/pos/profile/avatar', [
            'avatar' => UploadedFile::fake()->image('foto.jpg', 1200, 800),
        ])->assertRedirect(route('pos.profile'));

        $path = $this->kasir->fresh()->avatar_path;

        $this->assertNotNull($path);
        Storage::disk('public')->assertExists($path);

        // A phone photo kept at full size would be re-sent on every page
        // that shows the little avatar chip.
        [$width, $height] = getimagesize(Storage::disk('public')->path($path));

        $this->assertSame(320, $width);
        $this->assertSame(320, $height);
    }

    public function test_avatar_url_is_root_relative(): void
    {
        $this->actingAs($this->kasir, 'pos');

        $this->post('/pos/profile/avatar', [
            'avatar' => UploadedFile::fake()->image('foto.jpg', 400, 400),
        ]);

        // An absolute URL built from APP_URL breaks whenever that setting
        // disagrees with the host actually being browsed.
        $this->assertStringStartsWith('/uploads/avatars/', $this->kasir->fresh()->avatarUrl());
    }

    public function test_replacing_an_avatar_removes_the_previous_file(): void
    {
        $this->actingAs($this->kasir, 'pos');

        $this->post('/pos/profile/avatar', ['avatar' => UploadedFile::fake()->image('satu.jpg', 400, 400)]);
        $first = $this->kasir->fresh()->avatar_path;

        $this->post('/pos/profile/avatar', ['avatar' => UploadedFile::fake()->image('dua.jpg', 400, 400)]);
        $second = $this->kasir->fresh()->avatar_path;

        $this->assertNotSame($first, $second);
        Storage::disk('public')->assertMissing($first);
        Storage::disk('public')->assertExists($second);
    }

    public function test_avatar_can_be_removed(): void
    {
        $this->actingAs($this->kasir, 'pos');

        $this->post('/pos/profile/avatar', ['avatar' => UploadedFile::fake()->image('foto.jpg', 400, 400)]);
        $path = $this->kasir->fresh()->avatar_path;

        $this->delete('/pos/profile/avatar')->assertRedirect();

        $this->assertNull($this->kasir->fresh()->avatar_path);
        Storage::disk('public')->assertMissing($path);
    }

    public function test_non_images_are_rejected(): void
    {
        $this->actingAs($this->kasir, 'pos');

        // Uploads live under the document root, so anything executable
        // getting through would be a serious problem.
        $this->post('/pos/profile/avatar', [
            'avatar' => UploadedFile::fake()->create('shell.php', 40, 'application/x-php'),
        ])->assertSessionHasErrors('avatar');

        $this->assertNull($this->kasir->fresh()->avatar_path);
    }

    public function test_oversized_images_are_rejected(): void
    {
        $this->actingAs($this->kasir, 'pos');

        $this->post('/pos/profile/avatar', [
            'avatar' => UploadedFile::fake()->create('besar.jpg', 5000, 'image/jpeg'),
        ])->assertSessionHasErrors('avatar');
    }

    public function test_one_operator_cannot_touch_another_profile(): void
    {
        // There is no user id in any of these routes — they always act on
        // whoever is signed in, which is what keeps them safe.
        $this->actingAs($this->kasir, 'pos');

        $this->put('/pos/profile', [
            'name' => 'Diretas',
            'username' => $this->kasir->username,
            'email' => $this->kasir->email,
        ])->assertRedirect();

        $this->assertSame('Diretas', $this->kasir->fresh()->name);
        $this->assertNotSame('Diretas', $this->supervisor->fresh()->name);
    }
}
