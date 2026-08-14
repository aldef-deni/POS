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

if (! function_exists('current_tenant')) {
    function current_tenant(): ?\App\Models\Tenant
    {
        return app(Tenancy::class)->get();
    }
}

if (! function_exists('pos_user')) {
    /** The operator signed in at the terminal, if any. */
    function pos_user(): ?\App\Models\User
    {
        return auth('pos')->user();
    }
}
