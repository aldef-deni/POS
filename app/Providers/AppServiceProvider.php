<?php

namespace App\Providers;

use App\Support\Tenancy;
use Illuminate\Database\Eloquent\Model;
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

        $this->registerBladeDirectives();
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
