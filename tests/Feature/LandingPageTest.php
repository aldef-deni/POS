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

    public function test_the_footer_carries_aldef_tech_contact_not_the_tenant(): void
    {
        // This page sells the product, so the way to reach someone about it is
        // Aldef Tech's — never the contact of whichever store happens to be
        // loaded in the request.
        $this->tenant->update([
            'phone' => '021-555-0188',
            'email' => 'halo@tokouji.id',
            'address' => 'Jl. Merdeka No. 88',
        ]);

        $this->get('/')
            ->assertOk()
            ->assertSee(config('brand.email'))
            ->assertSee(config('brand.phone'))
            ->assertDontSee('021-555-0188')
            ->assertDontSee('halo@tokouji.id')
            ->assertDontSee('Jl. Merdeka No. 88');
    }

    public function test_the_header_and_footer_show_the_aldef_lockup(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertSee('site-brand__logo', false)
            ->assertSee('site-footer__logo', false)
            ->assertSee('aldef-landscape.png', false);
    }

    public function test_every_page_points_at_the_site_icons(): void
    {
        foreach (['/', '/admin/login', '/pos/login'] as $path) {
            $this->get($path)
                ->assertOk()
                ->assertSee('favicon.ico', false)
                ->assertSee('apple-touch-icon.png', false)
                ->assertSee('site.webmanifest', false);
        }
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
