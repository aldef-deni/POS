<?php

namespace Tests\Feature;

use Tests\PosTestCase;

/**
 * The public front door at pos.aldeftech.com.
 *
 * It is the one page anyone can open without signing in, so the things worth
 * guarding are that it stays open, that it points at both entrances, and that
 * it never becomes a place where store data leaks to a stranger.
 */
class LandingPageTest extends PosTestCase
{
    public function test_the_landing_page_is_public(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertSee($this->tenant->name);
    }

    public function test_it_offers_both_entrances(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertSee(route('pos.login'))
            ->assertSee(route('admin.login'));
    }

    public function test_it_carries_a_header_and_a_footer(): void
    {
        $page = $this->get('/')->assertOk();

        $page->assertSee('site-header', false)
            ->assertSee('site-footer', false)
            ->assertSee('Aldef Tech');
    }

    public function test_it_describes_every_role(): void
    {
        $page = $this->get('/')->assertOk();

        foreach (\App\Support\Role::all() as $role) {
            $page->assertSee($role->label());
        }
    }

    public function test_a_signed_in_manager_is_sent_straight_to_the_dashboard(): void
    {
        // No point showing "Masuk" to someone who already has a session.
        $this->actingAs($this->owner, 'web')
            ->get('/')
            ->assertOk()
            ->assertSee(route('admin.dashboard'))
            ->assertDontSee(route('admin.login'));
    }

    public function test_it_keeps_the_store_contact_details_it_is_given(): void
    {
        $this->tenant->update([
            'phone' => '021-555-0188',
            'email' => 'halo@tokouji.id',
            'address' => 'Jl. Merdeka No. 88',
        ]);

        $this->get('/')
            ->assertOk()
            ->assertSee('021-555-0188')
            ->assertSee('halo@tokouji.id')
            ->assertSee('Jl. Merdeka No. 88');
    }

    public function test_it_never_exposes_the_catalogue_or_the_operators(): void
    {
        // A marketing page has no business naming staff or listing stock.
        $this->get('/')
            ->assertOk()
            ->assertDontSee($this->kasir->name)
            ->assertDontSee($this->owner->name);
    }
}
