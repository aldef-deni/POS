<?php

namespace App\Support;

use App\Models\Outlet;

/**
 * The branch a request is acting on.
 *
 * At the terminal this is fixed to the cashier's own outlet and can never be
 * changed from the browser — that is what keeps one branch from touching
 * another's stock. On the dashboard an Owner may leave it unset, which means
 * "all outlets" and is only ever used for reading.
 */
class OutletContext
{
    protected ?Outlet $outlet = null;

    public function set(?Outlet $outlet): void
    {
        $this->outlet = $outlet;
    }

    public function get(): ?Outlet
    {
        return $this->outlet;
    }

    public function id(): ?int
    {
        return $this->outlet?->id;
    }

    public function has(): bool
    {
        return $this->outlet !== null;
    }

    public function name(): string
    {
        return $this->outlet?->name ?? 'Semua Outlet';
    }

    public function forget(): void
    {
        $this->outlet = null;
    }

    /**
     * Run a callback across every outlet, restoring the previous context
     * afterwards even if the callback throws.
     */
    public function withoutScope(callable $callback): mixed
    {
        $previous = $this->outlet;
        $this->outlet = null;

        try {
            return $callback();
        } finally {
            $this->outlet = $previous;
        }
    }
}
