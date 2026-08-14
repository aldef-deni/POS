<?php

namespace App\Services;

use App\Models\Product;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\SalePayment;
use App\Models\Shift;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Every figure the dashboard and the report module display.
 *
 * All queries run through the tenant global scope, and revenue figures only
 * ever count sales still in `completed` state so a void disappears from the
 * books the moment it is approved.
 */
class ReportService
{
    /** Inclusive day range as real timestamps. */
    protected function range(string $from, string $to): array
    {
        return [
            Carbon::parse($from)->startOfDay(),
            Carbon::parse($to)->endOfDay(),
        ];
    }

    protected function completedSales(string $from, string $to)
    {
        [$start, $end] = $this->range($from, $to);

        // Columns are table-qualified because callers join `users` onto this
        // builder, and both tables carry `created_at` / `status`.
        return Sale::where('sales.status', 'completed')
            ->whereBetween('sales.created_at', [$start, $end]);
    }

    /** Headline numbers for a period. */
    public function summary(string $from, string $to): array
    {
        $row = $this->completedSales($from, $to)
            ->selectRaw('
                COUNT(*) as transactions,
                COALESCE(SUM(total), 0) as revenue,
                COALESCE(SUM(subtotal), 0) as gross,
                COALESCE(SUM(discount_amount), 0) as discount,
                COALESCE(SUM(tax_amount), 0) as tax,
                COALESCE(SUM(service_charge_amount), 0) as service_charge,
                COALESCE(SUM(cost_total), 0) as cost,
                COALESCE(SUM(profit), 0) as profit,
                COALESCE(SUM(total_qty), 0) as qty
            ')
            ->first();

        $transactions = (int) $row->transactions;
        $revenue = (float) $row->revenue;

        [$start, $end] = $this->range($from, $to);

        $voided = Sale::where('status', 'voided')
            ->whereBetween('created_at', [$start, $end]);

        return [
            'transactions' => $transactions,
            'revenue' => $revenue,
            'gross' => (float) $row->gross,
            'discount' => (float) $row->discount,
            'tax' => (float) $row->tax,
            'service_charge' => (float) $row->service_charge,
            'cost' => (float) $row->cost,
            'profit' => (float) $row->profit,
            'qty' => (float) $row->qty,
            'average_basket' => $transactions > 0 ? $revenue / $transactions : 0.0,
            'margin_percent' => $revenue > 0 ? ((float) $row->profit / $revenue) * 100 : 0.0,
            'voided_count' => (clone $voided)->count(),
            'voided_value' => (float) (clone $voided)->sum('total'),
        ];
    }

    /** Revenue and profit per calendar day, gap-filled across the range. */
    public function dailySeries(string $from, string $to): Collection
    {
        $rows = $this->completedSales($from, $to)
            ->selectRaw('DATE(created_at) as day,
                COUNT(*) as transactions,
                COALESCE(SUM(total), 0) as revenue,
                COALESCE(SUM(profit), 0) as profit')
            ->groupBy('day')
            ->orderBy('day')
            ->get()
            ->keyBy(fn ($row) => (string) $row->day);

        $series = collect();
        $cursor = Carbon::parse($from)->startOfDay();
        $end = Carbon::parse($to)->startOfDay();

        while ($cursor->lte($end)) {
            $key = $cursor->toDateString();
            $row = $rows->get($key);

            $series->push([
                'date' => $key,
                'label' => $cursor->translatedFormat('d M'),
                'transactions' => (int) ($row->transactions ?? 0),
                'revenue' => (float) ($row->revenue ?? 0),
                'profit' => (float) ($row->profit ?? 0),
            ]);

            $cursor->addDay();
        }

        return $series;
    }

    /** Hour-of-day distribution, useful for staffing decisions. */
    public function hourlyDistribution(string $from, string $to): Collection
    {
        $rows = $this->completedSales($from, $to)
            ->selectRaw('HOUR(created_at) as hour, COUNT(*) as transactions, COALESCE(SUM(total),0) as revenue')
            ->groupBy('hour')
            ->pluck('revenue', 'hour');

        return collect(range(0, 23))->map(fn ($hour) => [
            'hour' => $hour,
            'label' => str_pad((string) $hour, 2, '0', STR_PAD_LEFT).':00',
            'revenue' => (float) ($rows[$hour] ?? 0),
        ]);
    }

    /** Sales per product, ranked by revenue. */
    public function productPerformance(string $from, string $to, int $limit = 0): Collection
    {
        [$start, $end] = $this->range($from, $to);

        $query = SaleItem::query()
            ->join('sales', 'sales.id', '=', 'sale_items.sale_id')
            ->leftJoin('products', 'products.id', '=', 'sale_items.product_id')
            ->leftJoin('categories', 'categories.id', '=', 'products.category_id')
            ->where('sales.tenant_id', app(\App\Support\Tenancy::class)->id())
            ->where('sales.status', 'completed')
            ->whereBetween('sales.created_at', [$start, $end])
            ->selectRaw('
                sale_items.product_id,
                MAX(sale_items.sku) as sku,
                MAX(sale_items.name) as name,
                MAX(categories.name) as category,
                SUM(sale_items.qty) as qty,
                SUM(sale_items.line_total) as revenue,
                SUM(sale_items.cost_price * sale_items.qty) as cost,
                SUM(sale_items.line_total - (sale_items.cost_price * sale_items.qty)) as profit,
                COUNT(DISTINCT sales.id) as transactions
            ')
            ->groupBy('sale_items.product_id')
            ->orderByDesc('revenue');

        if ($limit > 0) {
            $query->limit($limit);
        }

        return $query->get()->map(fn ($row) => [
            'product_id' => $row->product_id,
            'sku' => $row->sku,
            'name' => $row->name,
            'category' => $row->category ?? '—',
            'qty' => (float) $row->qty,
            'revenue' => (float) $row->revenue,
            'cost' => (float) $row->cost,
            'profit' => (float) $row->profit,
            'transactions' => (int) $row->transactions,
            'margin_percent' => $row->revenue > 0 ? ((float) $row->profit / (float) $row->revenue) * 100 : 0,
        ]);
    }

    /** Sales rolled up by product category. */
    public function categoryPerformance(string $from, string $to): Collection
    {
        [$start, $end] = $this->range($from, $to);

        return SaleItem::query()
            ->join('sales', 'sales.id', '=', 'sale_items.sale_id')
            ->leftJoin('products', 'products.id', '=', 'sale_items.product_id')
            ->leftJoin('categories', 'categories.id', '=', 'products.category_id')
            ->where('sales.tenant_id', app(\App\Support\Tenancy::class)->id())
            ->where('sales.status', 'completed')
            ->whereBetween('sales.created_at', [$start, $end])
            ->selectRaw('
                COALESCE(categories.name, \'Tanpa Kategori\') as category,
                COALESCE(categories.color, \'#94A3B8\') as color,
                SUM(sale_items.qty) as qty,
                SUM(sale_items.line_total) as revenue,
                SUM(sale_items.line_total - (sale_items.cost_price * sale_items.qty)) as profit
            ')
            ->groupBy('category', 'color')
            ->orderByDesc('revenue')
            ->get()
            ->map(fn ($row) => [
                'category' => $row->category,
                'color' => $row->color,
                'qty' => (float) $row->qty,
                'revenue' => (float) $row->revenue,
                'profit' => (float) $row->profit,
            ]);
    }

    /** Per-cashier takings. */
    public function cashierPerformance(string $from, string $to): Collection
    {
        return $this->completedSales($from, $to)
            ->join('users', 'users.id', '=', 'sales.user_id')
            ->selectRaw('
                sales.user_id,
                MAX(users.name) as name,
                MAX(users.role) as role,
                COUNT(*) as transactions,
                COALESCE(SUM(sales.total), 0) as revenue,
                COALESCE(SUM(sales.profit), 0) as profit,
                COALESCE(SUM(sales.discount_amount), 0) as discount
            ')
            ->groupBy('sales.user_id')
            ->orderByDesc('revenue')
            ->get()
            ->map(fn ($row) => [
                'user_id' => $row->user_id,
                'name' => $row->name,
                'role' => $row->role,
                'transactions' => (int) $row->transactions,
                'revenue' => (float) $row->revenue,
                'profit' => (float) $row->profit,
                'discount' => (float) $row->discount,
                'average_basket' => $row->transactions > 0 ? (float) $row->revenue / (int) $row->transactions : 0,
            ]);
    }

    /** Tender mix. */
    public function paymentBreakdown(string $from, string $to): Collection
    {
        [$start, $end] = $this->range($from, $to);

        $rows = SalePayment::query()
            ->join('sales', 'sales.id', '=', 'sale_payments.sale_id')
            ->where('sales.tenant_id', app(\App\Support\Tenancy::class)->id())
            ->where('sales.status', 'completed')
            ->whereBetween('sales.created_at', [$start, $end])
            ->selectRaw('sale_payments.method, COUNT(*) as count, COALESCE(SUM(sale_payments.amount),0) as amount')
            ->groupBy('sale_payments.method')
            ->orderByDesc('amount')
            ->get();

        $total = (float) $rows->sum('amount');

        return $rows->map(fn ($row) => [
            'method' => $row->method,
            'label' => SalePayment::methods()[$row->method] ?? $row->method,
            'count' => (int) $row->count,
            'amount' => (float) $row->amount,
            'share' => $total > 0 ? ((float) $row->amount / $total) * 100 : 0,
        ]);
    }

    /** Current inventory position and its value at cost and at retail. */
    public function inventory(): array
    {
        $products = Product::with('category')
            ->where('track_stock', true)
            ->orderBy('name')
            ->get();

        return [
            'products' => $products->map(fn (Product $p) => [
                'id' => $p->id,
                'sku' => $p->sku,
                'name' => $p->name,
                'category' => $p->category?->name ?? '—',
                'unit' => $p->unit,
                'stock' => (float) $p->stock,
                'min_stock' => (float) $p->min_stock,
                'cost_price' => (float) $p->cost_price,
                'price' => (float) $p->price,
                'value_cost' => (float) $p->stock * (float) $p->cost_price,
                'value_retail' => (float) $p->stock * (float) $p->price,
                'status' => $p->isOutOfStock() ? 'habis' : ($p->isLowStock() ? 'menipis' : 'aman'),
            ]),
            'total_value_cost' => (float) $products->sum(fn ($p) => (float) $p->stock * (float) $p->cost_price),
            'total_value_retail' => (float) $products->sum(fn ($p) => (float) $p->stock * (float) $p->price),
            'low_stock_count' => $products->filter->isLowStock()->count(),
            'out_of_stock_count' => $products->filter->isOutOfStock()->count(),
        ];
    }

    /** Closed and open drawer sessions in the period. */
    public function shifts(string $from, string $to): Collection
    {
        [$start, $end] = $this->range($from, $to);

        return Shift::with(['user', 'closedBy'])
            ->whereBetween('opened_at', [$start, $end])
            ->orderByDesc('opened_at')
            ->get();
    }

    /** Voided transactions, for loss-prevention review. */
    public function voids(string $from, string $to): Collection
    {
        [$start, $end] = $this->range($from, $to);

        return Sale::with(['user', 'voidedBy'])
            ->where('status', 'voided')
            ->whereBetween('created_at', [$start, $end])
            ->orderByDesc('voided_at')
            ->get();
    }

    /** Full transaction list for the detail report. */
    public function salesList(string $from, string $to, array $filters = []): Collection
    {
        $query = $this->completedSales($from, $to)->with(['user', 'customer', 'payments']);

        if (! empty($filters['user_id'])) {
            $query->where('user_id', $filters['user_id']);
        }

        if (! empty($filters['payment_method'])) {
            $query->whereHas('payments', fn ($q) => $q->where('method', $filters['payment_method']));
        }

        return $query->orderBy('created_at')->get();
    }

    /**
     * Compare a period against the equally long window immediately before it,
     * so the dashboard can show a trend arrow.
     */
    public function comparison(string $from, string $to): array
    {
        $current = $this->summary($from, $to);

        $start = Carbon::parse($from)->startOfDay();
        $end = Carbon::parse($to)->endOfDay();
        $days = max(1, $start->diffInDays($end) + 1);

        $previous = $this->summary(
            $start->copy()->subDays($days)->toDateString(),
            $start->copy()->subDay()->toDateString(),
        );

        $delta = function (float $now, float $before): float {
            if ($before <= 0) {
                return $now > 0 ? 100.0 : 0.0;
            }

            return (($now - $before) / $before) * 100;
        };

        return [
            'current' => $current,
            'previous' => $previous,
            'revenue_delta' => $delta($current['revenue'], $previous['revenue']),
            'transactions_delta' => $delta($current['transactions'], $previous['transactions']),
            'profit_delta' => $delta($current['profit'], $previous['profit']),
            'basket_delta' => $delta($current['average_basket'], $previous['average_basket']),
        ];
    }
}
