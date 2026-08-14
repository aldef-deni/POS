<?php

namespace App\Services;

use App\Models\Category;
use App\Models\Tenant;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Builds the product ID (SKU) from the pattern an Owner configures on the
 * dashboard, plus the matching scannable barcode value.
 *
 * The pattern is assembled from four optional segments:
 *
 *     PREFIX - CATEGORY - DATE - SEQUENCE
 *     PRD    - BVG      - 2608 - 0042        =>  PRD-BVG-2608-0042
 *
 * The running number lives on the tenant row and is handed out under a row
 * lock, so two people saving a product at the same moment can never be given
 * the same ID.
 */
class SkuGenerator
{
    /**
     * Reserve and return the next SKU for a product in the given category.
     * Consumes one sequence number.
     */
    public function generate(Tenant $tenant, ?Category $category = null): string
    {
        $sequence = $this->consumeSequence($tenant);

        return $this->compose($tenant, $category, $sequence);
    }

    /**
     * Render what the next SKU would look like without consuming it. Used by
     * the settings screen to preview a pattern change live.
     */
    public function preview(Tenant $tenant, ?Category $category = null, ?array $overrides = null): string
    {
        $draft = $overrides ? (clone $tenant)->forceFill($overrides) : $tenant;

        return $this->compose($draft, $category, (int) $draft->sku_next_number);
    }

    /**
     * Atomically take the next number from the tenant counter.
     */
    protected function consumeSequence(Tenant $tenant): int
    {
        return DB::transaction(function () use ($tenant) {
            $locked = Tenant::whereKey($tenant->getKey())->lockForUpdate()->first();

            $current = (int) ($locked->sku_next_number ?: 1);

            $locked->forceFill(['sku_next_number' => $current + 1])->save();

            // Keep the in-memory instance in step with the database.
            $tenant->setAttribute('sku_next_number', $current + 1);

            return $current;
        });
    }

    /** Assemble the segments into the final string. */
    protected function compose(Tenant $tenant, ?Category $category, int $sequence): string
    {
        $separator = $tenant->sku_separator ?? '-';
        $segments = [];

        if (filled($tenant->sku_prefix)) {
            $segments[] = strtoupper($tenant->sku_prefix);
        }

        if ($tenant->sku_include_category) {
            $segments[] = $category?->skuCode() ?? 'GEN';
        }

        if ($date = $this->dateSegment($tenant->sku_date_segment)) {
            $segments[] = $date;
        }

        $segments[] = str_pad(
            (string) $sequence,
            max(1, (int) $tenant->sku_sequence_length),
            '0',
            STR_PAD_LEFT
        );

        return implode($separator, array_filter($segments, fn ($s) => $s !== ''));
    }

    protected function dateSegment(?string $mode): ?string
    {
        $now = Carbon::now();

        return match ($mode) {
            'yy' => $now->format('y'),
            'yymm' => $now->format('ym'),
            'yymmdd' => $now->format('ymd'),
            default => null,
        };
    }

    /**
     * Produce the scannable value stored on the product.
     *
     * Code 128 encodes the SKU verbatim, which keeps the printed label human
     * readable. EAN-13 needs exactly 13 digits, so we mint an in-store code
     * (GS1 reserves the "2" prefix for internal use) and append the checksum.
     */
    public function barcodeValue(Tenant $tenant, string $sku, int $sequence): string
    {
        if ($tenant->barcode_type === 'EAN13') {
            $body = '2'.str_pad((string) ($tenant->id % 100), 2, '0', STR_PAD_LEFT)
                .str_pad((string) $sequence, 9, '0', STR_PAD_LEFT);

            $body = substr($body, 0, 12);

            return $body.$this->ean13CheckDigit($body);
        }

        return $sku;
    }

    /** Standard EAN-13 modulo-10 check digit over the first 12 digits. */
    public function ean13CheckDigit(string $twelveDigits): int
    {
        $sum = 0;

        foreach (str_split($twelveDigits) as $index => $digit) {
            $sum += (int) $digit * ($index % 2 === 0 ? 1 : 3);
        }

        return (10 - ($sum % 10)) % 10;
    }

    /**
     * A human-readable explanation of the current pattern, shown next to the
     * preview on the settings screen.
     */
    public function describe(Tenant $tenant): string
    {
        $parts = [];

        if (filled($tenant->sku_prefix)) {
            $parts[] = 'PREFIX';
        }

        if ($tenant->sku_include_category) {
            $parts[] = 'KATEGORI';
        }

        $parts[] = match ($tenant->sku_date_segment) {
            'yy' => 'TAHUN',
            'yymm' => 'TAHUN+BULAN',
            'yymmdd' => 'TANGGAL',
            default => null,
        };

        $parts[] = 'URUT('.$tenant->sku_sequence_length.' digit)';

        return implode(' '.($tenant->sku_separator ?? '-').' ', array_filter($parts));
    }
}
