<?php

namespace App\Support;

use App\Models\Tenant;

/**
 * Holds the tenant for the current request.
 *
 * Registered as a singleton, so the tenant is resolved once by middleware
 * and every model scope, service and view reads the same instance. When no
 * tenant is set (console commands, seeders) scoping is simply skipped.
 */
class Tenancy
{
    protected ?Tenant $tenant = null;

    public function set(?Tenant $tenant): void
    {
        $this->tenant = $tenant;
    }

    public function get(): ?Tenant
    {
        return $this->tenant;
    }

    public function id(): ?int
    {
        return $this->tenant?->id;
    }

    public function has(): bool
    {
        return $this->tenant !== null;
    }

    public function forget(): void
    {
        $this->tenant = null;
    }

    /**
     * Run a callback with scoping temporarily disabled, restoring whatever
     * tenant was active afterwards even if the callback throws.
     */
    public function withoutScope(callable $callback): mixed
    {
        $previous = $this->tenant;
        $this->tenant = null;

        try {
            return $callback();
        } finally {
            $this->tenant = $previous;
        }
    }
}
