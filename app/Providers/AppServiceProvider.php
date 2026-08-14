<?php

namespace App\Providers;

use App\Support\OutletContext;
use App\Support\Tenancy;
use Illuminate\Auth\Middleware\RedirectIfAuthenticated;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // One tenant per request, shared by scopes, services and views.
        $this->app->singleton(Tenancy::class, fn () => new Tenancy());

        // The branch the request acts on. Fixed at the terminal, selectable
        // on the dashboard, unset meaning "all outlets".
        $this->app->singleton(OutletContext::class, fn () => new OutletContext());
    }

    public function boot(): void
    {
        // Keeps unique indexes inside the 767-byte key limit on the older
        // MySQL builds that shared hosting still ships.
        Schema::defaultStringLength(191);

        Model::preventLazyLoading(false);

        // The UI ships its own CSS, so pagination uses a matching template
        // rather than Laravel's Tailwind/Bootstrap defaults.
        Paginator::defaultView('components.pagination');
        Paginator::defaultSimpleView('components.pagination');

        $this->registerGuestRedirect();
        $this->registerBladeDirectives();
    }

    /**
     * Decide where an already-signed-in visitor goes when they open a
     * sign-in screen again.
     *
     * Laravel's default hunts for a route whose URI is "dashboard" and sends
     * everyone there. For this app that is wrong: an operator who reopens
     * /pos/login would be bounced to /dashboard, refused (no `web` session),
     * and dumped on /admin/login — a back-office page a cashier must never
     * be sent to. Route each visitor back to the door they knocked on.
     */
    protected function registerGuestRedirect(): void
    {
        RedirectIfAuthenticated::redirectUsing(function (Request $request) {
            if ($request->is('pos', 'pos/*')) {
                return route('pos.index');
            }

            return route('admin.dashboard');
        });
    }

    /**
     * Permission-aware Blade directives, so templates ask the same role
     * matrix the middleware does.
     */
    protected function registerBladeDirectives(): void
    {
        // @allow('settings.manage') ... @endallow
        Blade::if('allow', function (string $permission) {
            $user = auth('web')->user() ?? auth('pos')->user();

            return (bool) $user?->hasPermission($permission);
        });

        // @role('Owner', 'Supervisor') ... @endrole
        Blade::if('role', function (string ...$roles) {
            $user = auth('web')->user() ?? auth('pos')->user();

            return $user && in_array($user->role?->value, $roles, true);
        });
    }
}
