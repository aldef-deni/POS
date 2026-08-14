<?php

namespace App\Services;

use App\Models\ActivityLog;
use App\Models\Customer;
use App\Models\Product;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\SalePayment;
use App\Models\Shift;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Turns a cart into a recorded sale.
 *
 * The browser computes totals too, but only for display — the figures written
 * to the database are always recalculated here from current product prices,
 * so a tampered payload cannot change what the shop actually charges.
 */
class CheckoutService
{
    public function __construct(
        protected StockService $stock,
    ) {}

    /**
     * Price a cart without persisting anything.
     *
     * @param  array<int,array{product_id:int,qty:float,discount_amount?:float,note?:string}>  $items
     * @return array{lines:array<int,array>,totals:array<string,float>}
     */
    public function calculate(Tenant $tenant, array $items, string $discountType = 'none', float $discountValue = 0): array
    {
        $lines = [];
        $subtotal = 0.0;

        foreach ($items as $row) {
            $product = $row['product'] ?? Product::find($row['product_id']);

            if (! $product) {
                continue;
            }

            $qty = max(0, (float) ($row['qty'] ?? 1));

            if ($qty <= 0) {
                continue;
            }

            $unitPrice = $product->priceForQty($qty);
            $lineDiscount = max(0, (float) ($row['discount_amount'] ?? 0));
            $gross = $unitPrice * $qty;
            $lineDiscount = min($lineDiscount, $gross);
            $lineTotal = $gross - $lineDiscount;

            $subtotal += $lineTotal;

            $lines[] = [
                'product' => $product,
                'qty' => $qty,
                'unit_price' => $unitPrice,
                'cost_price' => (float) $product->cost_price,
                'discount_amount' => $lineDiscount,
                'line_total' => $lineTotal,
                'tax_amount' => 0.0,
                'note' => $row['note'] ?? null,
            ];
        }

        // Order-level discount, never more than the basket itself.
        $discountAmount = match ($discountType) {
            'percent' => $subtotal * (min(100, max(0, $discountValue)) / 100),
            'amount' => min(max(0, $discountValue), $subtotal),
            default => 0.0,
        };

        $afterDiscount = $subtotal - $discountAmount;

        $serviceCharge = $afterDiscount * ((float) $tenant->service_charge_percent / 100);

        // Spread the basket discount across lines so per-item tax stays fair.
        $taxRate = (float) $tenant->tax_percent / 100;
        $taxTotal = 0.0;

        foreach ($lines as $index => $line) {
            $share = $subtotal > 0 ? $line['line_total'] / $subtotal : 0;
            $lineNet = $line['line_total'] - ($discountAmount * $share);

            $lineTax = 0.0;

            if ($tenant->tax_enabled && ! $line['product']->tax_exempt && $taxRate > 0) {
                $lineTax = $tenant->tax_inclusive
                    ? $lineNet - ($lineNet / (1 + $taxRate))
                    : $lineNet * $taxRate;
            }

            $lines[$index]['tax_amount'] = round($lineTax, 2);
            $taxTotal += $lineTax;
        }

        $taxTotal = round($taxTotal, 2);

        // Inclusive tax is already inside the line prices, so it is reported
        // but not added again.
        $rawTotal = $afterDiscount + $serviceCharge
            + ($tenant->tax_enabled && ! $tenant->tax_inclusive ? $taxTotal : 0);

        $roundedTotal = $tenant->roundTotal($rawTotal);

        $costTotal = array_reduce(
            $lines,
            fn ($carry, $line) => $carry + ($line['cost_price'] * $line['qty']),
            0.0
        );

        return [
            'lines' => $lines,
            'totals' => [
                'subtotal' => round($subtotal, 2),
                'discount_amount' => round($discountAmount, 2),
                'service_charge_amount' => round($serviceCharge, 2),
                'tax_amount' => $taxTotal,
                'rounding_amount' => round($roundedTotal - $rawTotal, 2),
                'total' => round($roundedTotal, 2),
                'cost_total' => round($costTotal, 2),
                'profit' => round($afterDiscount - $costTotal, 2),
                'item_count' => count($lines),
                'total_qty' => round(array_sum(array_column($lines, 'qty')), 3),
            ],
        ];
    }

    /**
     * Persist a completed sale: items, tenders, stock ledger, shift totals
     * and customer loyalty, all in one transaction.
     *
     * @throws ValidationException when stock is insufficient or underpaid.
     */
    public function checkout(
        Tenant $tenant,
        User $cashier,
        Shift $shift,
        array $payload,
    ): Sale {
        $items = $payload['items'] ?? [];

        if (empty($items)) {
            throw ValidationException::withMessages([
                'items' => 'Keranjang masih kosong.',
            ]);
        }

        $priced = $this->calculate(
            $tenant,
            $items,
            $payload['discount_type'] ?? 'none',
            (float) ($payload['discount_value'] ?? 0),
        );

        $lines = $priced['lines'];
        $totals = $priced['totals'];

        if (empty($lines)) {
            throw ValidationException::withMessages([
                'items' => 'Tidak ada produk valid dalam keranjang.',
            ]);
        }

        $this->assertStockAvailable($tenant, $lines);

        $payments = $this->normalisePayments($payload['payments'] ?? []);
        $paid = round(array_sum(array_column($payments, 'amount')), 2);

        if ($paid + 0.001 < $totals['total']) {
            throw ValidationException::withMessages([
                'payments' => 'Jumlah pembayaran kurang dari total belanja.',
            ]);
        }

        // Change is only ever given back in cash.
        $cashPaid = round(array_sum(array_map(
            fn ($p) => $p['method'] === 'cash' ? $p['amount'] : 0,
            $payments
        )), 2);

        $change = min(round($paid - $totals['total'], 2), $cashPaid);
        $change = max(0, $change);

        return DB::transaction(function () use (
            $tenant, $cashier, $shift, $payload, $lines, $totals, $payments, $paid, $change
        ) {
            $sale = Sale::create([
                'tenant_id' => $tenant->id,
                'invoice_number' => $this->nextInvoiceNumber($tenant),
                'shift_id' => $shift->id,
                'user_id' => $cashier->id,
                'customer_id' => $payload['customer_id'] ?? null,
                'subtotal' => $totals['subtotal'],
                'discount_type' => $payload['discount_type'] ?? 'none',
                'discount_value' => (float) ($payload['discount_value'] ?? 0),
                'discount_amount' => $totals['discount_amount'],
                'tax_percent' => $tenant->tax_enabled ? $tenant->tax_percent : 0,
                'tax_amount' => $totals['tax_amount'],
                'service_charge_amount' => $totals['service_charge_amount'],
                'rounding_amount' => $totals['rounding_amount'],
                'total' => $totals['total'],
                'paid_amount' => $paid,
                'change_amount' => $change,
                'item_count' => $totals['item_count'],
                'total_qty' => $totals['total_qty'],
                'cost_total' => $totals['cost_total'],
                'profit' => $totals['profit'],
                'status' => 'completed',
                'note' => $payload['note'] ?? null,
            ]);

            foreach ($lines as $line) {
                /** @var Product $product */
                $product = $line['product'];

                SaleItem::create([
                    'sale_id' => $sale->id,
                    'product_id' => $product->id,
                    'sku' => $product->sku,
                    'name' => $product->name,
                    'unit' => $product->unit,
                    'qty' => $line['qty'],
                    'unit_price' => $line['unit_price'],
                    'cost_price' => $line['cost_price'],
                    'discount_amount' => $line['discount_amount'],
                    'tax_amount' => $line['tax_amount'],
                    'line_total' => $line['line_total'],
                    'note' => $line['note'],
                ]);

                $this->stock->apply(
                    $product,
                    -$line['qty'],
                    'sale',
                    $sale,
                    'Penjualan '.$sale->invoice_number,
                    $cashier->id,
                );

                $product->increment('sold_count', (int) round($line['qty']));
            }

            foreach ($payments as $payment) {
                SalePayment::create([
                    'sale_id' => $sale->id,
                    'method' => $payment['method'],
                    'amount' => $payment['amount'],
                    'reference' => $payment['reference'] ?? null,
                ]);
            }

            $this->applyToShift($shift, $sale, $payments);
            $this->applyToCustomer($sale);

            return $sale->load(['items', 'payments', 'customer', 'user']);
        });
    }

    /**
     * Reverse a sale: return the stock, back out the shift totals and mark
     * the record voided. The sale row itself is never deleted.
     */
    public function void(Sale $sale, User $approver, string $reason): Sale
    {
        if ($sale->isVoided()) {
            throw ValidationException::withMessages([
                'sale' => 'Transaksi ini sudah dibatalkan.',
            ]);
        }

        return DB::transaction(function () use ($sale, $approver, $reason) {
            foreach ($sale->items as $item) {
                if ($item->product) {
                    $this->stock->apply(
                        $item->product,
                        (float) $item->qty,
                        'void_return',
                        $sale,
                        'Void '.$sale->invoice_number,
                        $approver->id,
                    );
                }
            }

            if ($shift = $sale->shift) {
                $cashPortion = (float) $sale->payments->where('method', 'cash')->sum('amount')
                    - (float) $sale->change_amount;
                $nonCashPortion = (float) $sale->payments->where('method', '!=', 'cash')->sum('amount');

                $shift->decrement('total_sales', (float) $sale->total);
                $shift->decrement('total_transactions');
                $shift->decrement('cash_sales', max(0, $cashPortion));
                $shift->decrement('non_cash_sales', $nonCashPortion);
                $shift->increment('total_refunds', (float) $sale->total);
                $shift->forceFill(['expected_cash' => $shift->fresh()->expectedCashNow()])->save();
            }

            $sale->forceFill([
                'status' => 'voided',
                'voided_at' => now(),
                'voided_by' => $approver->id,
                'void_reason' => $reason,
            ])->save();

            ActivityLog::record(
                'sale.void',
                "Void transaksi {$sale->invoice_number} — {$reason}",
                $sale,
                ['total' => (float) $sale->total],
                $approver,
            );

            return $sale;
        });
    }

    /** Reject the sale before it starts if any line would oversell. */
    protected function assertStockAvailable(Tenant $tenant, array $lines): void
    {
        if ($tenant->allow_negative_stock) {
            return;
        }

        foreach ($lines as $line) {
            /** @var Product $product */
            $product = $line['product'];

            if ($product->track_stock && (float) $product->stock < $line['qty']) {
                throw ValidationException::withMessages([
                    'items' => "Stok {$product->name} tidak mencukupi (tersisa "
                        .rtrim(rtrim(number_format((float) $product->stock, 3, ',', '.'), '0'), ',')
                        ." {$product->unit}).",
                ]);
            }
        }
    }

    /**
     * @return array<int,array{method:string,amount:float,reference:?string}>
     */
    protected function normalisePayments(array $payments): array
    {
        $valid = array_keys(SalePayment::methods());
        $result = [];

        foreach ($payments as $payment) {
            $method = $payment['method'] ?? 'cash';
            $amount = round((float) ($payment['amount'] ?? 0), 2);

            if (! in_array($method, $valid, true) || $amount <= 0) {
                continue;
            }

            $result[] = [
                'method' => $method,
                'amount' => $amount,
                'reference' => $payment['reference'] ?? null,
            ];
        }

        if (empty($result)) {
            throw ValidationException::withMessages([
                'payments' => 'Metode pembayaran belum dipilih.',
            ]);
        }

        return $result;
    }

    /**
     * Per-tenant, per-day running invoice number: INV-260814-0001.
     *
     * Called inside the checkout transaction; the tenant row lock serialises
     * concurrent tills so two receipts cannot share a number.
     */
    protected function nextInvoiceNumber(Tenant $tenant): string
    {
        Tenant::whereKey($tenant->id)->lockForUpdate()->first();

        $prefix = 'INV-'.Carbon::now()->format('ymd').'-';

        $last = Sale::withoutGlobalScope('tenant')
            ->where('tenant_id', $tenant->id)
            ->where('invoice_number', 'like', $prefix.'%')
            ->orderByDesc('invoice_number')
            ->value('invoice_number');

        $sequence = $last ? ((int) substr($last, -4)) + 1 : 1;

        return $prefix.str_pad((string) $sequence, 4, '0', STR_PAD_LEFT);
    }

    protected function applyToShift(Shift $shift, Sale $sale, array $payments): void
    {
        $cash = array_sum(array_map(
            fn ($p) => $p['method'] === 'cash' ? $p['amount'] : 0,
            $payments
        ));

        // Change handed back leaves the drawer, so it is netted off.
        $cashNet = max(0, $cash - (float) $sale->change_amount);
        $nonCash = array_sum(array_map(
            fn ($p) => $p['method'] !== 'cash' ? $p['amount'] : 0,
            $payments
        ));

        $shift->increment('total_sales', (float) $sale->total);
        $shift->increment('total_transactions');
        $shift->increment('cash_sales', $cashNet);
        $shift->increment('non_cash_sales', $nonCash);

        $shift->refresh();
        $shift->forceFill(['expected_cash' => $shift->expectedCashNow()])->save();
    }

    /** Award a simple loyalty point per 10.000 spent. */
    protected function applyToCustomer(Sale $sale): void
    {
        if (! $sale->customer_id) {
            return;
        }

        $customer = Customer::find($sale->customer_id);

        if (! $customer) {
            return;
        }

        $customer->increment('total_spent', (float) $sale->total);
        $customer->increment('visit_count');
        $customer->increment('points', (int) floor((float) $sale->total / 10000));
        $customer->forceFill(['last_visit_at' => now()])->save();
    }
}
