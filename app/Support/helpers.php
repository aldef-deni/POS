<?php

use App\Support\Tenancy;

if (! function_exists('money')) {
    /**
     * Format an amount in the tenant's currency, e.g. "Rp 1.250.000".
     * Rupiah has no minor unit in practice, so decimals are dropped unless
     * the amount actually has a fractional part.
     */
    function money(float|int|string|null $amount, bool $withSymbol = true): string
    {
        $amount = (float) ($amount ?? 0);
        $symbol = app(Tenancy::class)->get()?->currency_symbol ?? 'Rp';

        $decimals = fmod(abs($amount), 1) > 0.0001 ? 2 : 0;
        $formatted = number_format($amount, $decimals, ',', '.');

        return $withSymbol ? $symbol.' '.$formatted : $formatted;
    }
}

if (! function_exists('qty_label')) {
    /**
     * Trim trailing zeros from a decimal quantity: 2.000 => "2", 1.500 => "1,5".
     */
    function qty_label(float|int|string|null $qty): string
    {
        $value = (float) ($qty ?? 0);

        if (fmod(abs($value), 1) < 0.0001) {
            return number_format($value, 0, ',', '.');
        }

        return rtrim(rtrim(number_format($value, 3, ',', '.'), '0'), ',');
    }
}

if (! function_exists('percent_label')) {
    function percent_label(float|int|null $value, int $decimals = 1): string
    {
        return number_format((float) ($value ?? 0), $decimals, ',', '.').'%';
    }
}

if (! function_exists('asset_v')) {
    /**
     * Asset URL stamped with the file's own modification time.
     *
     * A fixed version string is worse than none: .htaccess tells browsers to
     * keep CSS for a week, so after a deploy they happily serve yesterday's
     * stylesheet against today's markup. Stamping with mtime makes the URL
     * change exactly when the file does, and never otherwise.
     */
    function asset_v(string $path): string
    {
        $full = public_path($path);

        // Deliberately not memoised in a static: under a long-running worker
        // that would freeze the stamp until the process restarted. PHP's own
        // stat cache already makes the repeat calls essentially free.
        $stamp = is_file($full) ? (string) filemtime($full) : '0';

        return asset($path).'?v='.$stamp;
    }
}

if (! function_exists('current_tenant')) {
    function current_tenant(): ?\App\Models\Tenant
    {
        return app(Tenancy::class)->get();
    }
}

if (! function_exists('current_outlet')) {
    /** The branch the request is acting on, or null for "all outlets". */
    function current_outlet(): ?\App\Models\Outlet
    {
        return app(\App\Support\OutletContext::class)->get();
    }
}

if (! function_exists('outlet_label')) {
    /** Human label for the active branch, used on printed documents. */
    function outlet_label(): string
    {
        return app(\App\Support\OutletContext::class)->name();
    }
}

if (! function_exists('pos_user')) {
    /** The operator signed in at the terminal, if any. */
    function pos_user(): ?\App\Models\User
    {
        return auth('pos')->user();
    }
}
