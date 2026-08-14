<?php

namespace Tests\Feature;

use Tests\PosTestCase;

/**
 * The access rules the brief is built on: the terminal stands alone, and a
 * cashier never reaches the management dashboard.
 */
class RoleAccessTest extends PosTestCase
{
    public function test_cashier_terminal_is_reachable_without_a_dashboard_session(): void
    {
        // No web guard session at all — the terminal sign-in must still load.
        $this->get('/pos/login')->assertOk()->assertSee('Masuk Kasir');
    }

    public function test_terminal_requires_its_own_sign_in(): void
    {
        $this->get('/pos')->assertRedirect(route('pos.login'));
    }

    public function test_signing_in_at_the_terminal_does_not_grant_a_dashboard_session(): void
    {
        $this->post('/pos/login', [
            'login' => 'kasir',
            'password' => 'rahasia123',
        ]);

        $this->assertTrue(auth('pos')->check(), 'operator harus masuk pada guard pos');
        $this->assertFalse(auth('web')->check(), 'guard dashboard tidak boleh ikut aktif');
    }

    public function test_cashier_is_redirected_away_from_the_dashboard(): void
    {
        $this->actingAs($this->kasir, 'pos');

        $this->get('/dashboard')->assertRedirect(route('admin.login'));
    }

    public function test_cashier_cannot_sign_in_to_the_dashboard(): void
    {
        $response = $this->post('/admin/login', [
            'login' => 'kasir',
            'password' => 'rahasia123',
        ]);

        $response->assertSessionHasErrors('login');
        $this->assertFalse(auth('web')->check());
    }

    public function test_owner_reaches_every_administration_screen(): void
    {
        $this->actingAs($this->owner, 'web');

        $this->get('/dashboard')->assertOk();
        $this->get('/dashboard/users')->assertOk();
        $this->get('/dashboard/settings')->assertOk();
        $this->get('/dashboard/reports')->assertOk();
    }

    public function test_supervisor_runs_operations_but_not_administration(): void
    {
        $this->actingAs($this->supervisor, 'web');

        // Allowed: day-to-day operations.
        $this->get('/dashboard')->assertOk();
        $this->get('/dashboard/products')->assertOk();
        $this->get('/dashboard/stock')->assertOk();
        $this->get('/dashboard/reports')->assertOk();

        // Denied: anything that changes how the business is configured.
        $this->get('/dashboard/users')->assertForbidden();
        $this->get('/dashboard/settings')->assertForbidden();
    }

    public function test_four_digit_pin_signs_the_operator_in(): void
    {
        // PIN length is variable (4–8), and the seeded operators use four
        // digits — the keypad must accept that without padding.
        $response = $this->post('/pos/login', [
            'user_id' => $this->kasir->id,
            'pin' => '1234',
        ]);

        $this->assertTrue(auth('pos')->check());
        $response->assertRedirect(route('pos.shift.open'));
    }

    public function test_pin_shorter_than_four_digits_is_rejected(): void
    {
        $this->post('/pos/login', [
            'user_id' => $this->kasir->id,
            'pin' => '123',
        ])->assertSessionHasErrors('pin');

        $this->assertFalse(auth('pos')->check());
    }

    public function test_wrong_pin_is_rejected(): void
    {
        $this->post('/pos/login', [
            'user_id' => $this->kasir->id,
            'pin' => '9999',
        ])->assertSessionHasErrors('pin');

        $this->assertFalse(auth('pos')->check());
    }

    public function test_deactivated_operator_cannot_use_the_terminal(): void
    {
        $this->kasir->update(['is_active' => false]);

        $this->actingAs($this->kasir, 'pos');

        $this->get('/pos')->assertRedirect(route('pos.login'));
    }
}
