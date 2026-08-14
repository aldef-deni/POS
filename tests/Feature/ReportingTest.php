<?php

namespace Tests\Feature;

use App\Models\Sale;
use App\Services\ReportService;
use Tests\PosTestCase;

/**
 * Requirement 5: complete reporting over a date range, exportable to PDF.
 */
class ReportingTest extends PosTestCase
{
    /** Record one real sale so the reports have something to aggregate. */
    protected function recordSale(int $qty = 2): Sale
    {
        $product = $this->makeProduct(['stock' => 100, 'price' => 25000, 'cost_price' => 10000]);
        $this->openShift();

        $this->actingAs($this->kasir, 'pos')
            ->postJson('/pos/checkout', [
                'items' => [['product_id' => $product->id, 'qty' => $qty]],
                'payments' => [['method' => 'cash', 'amount' => 200000]],
            ])->assertCreated();

        return Sale::firstOrFail();
    }

    public function test_summary_totals_match_the_recorded_sales(): void
    {
        $this->recordSale(2);

        $today = today()->toDateString();
        $summary = app(ReportService::class)->summary($today, $today);

        $this->assertSame(1, $summary['transactions']);
        $this->assertEquals(50000, $summary['revenue']);
        $this->assertEquals(30000, $summary['profit']);
        $this->assertEquals(50000, $summary['average_basket']);
    }

    public function test_date_range_excludes_sales_outside_it(): void
    {
        $this->recordSale();

        $reports = app(ReportService::class);

        $past = today()->subDays(10)->toDateString();
        $yesterday = today()->subDay()->toDateString();

        $this->assertSame(0, $reports->summary($past, $yesterday)['transactions']);
        $this->assertSame(1, $reports->summary(today()->toDateString(), today()->toDateString())['transactions']);
    }

    public function test_voided_sales_leave_the_revenue_figures(): void
    {
        $sale = $this->recordSale();

        $today = today()->toDateString();
        $reports = app(ReportService::class);

        $this->assertEquals(50000, $reports->summary($today, $today)['revenue']);

        app(\App\Services\CheckoutService::class)->void($sale, $this->supervisor, 'uji');

        $summary = $reports->summary($today, $today);

        $this->assertSame(0, $summary['transactions']);
        $this->assertEquals(0, $summary['revenue']);
        $this->assertSame(1, $summary['voided_count']);
    }

    public function test_every_report_screen_renders(): void
    {
        $this->recordSale();
        $this->actingAs($this->owner, 'web');

        foreach ([
            'summary', 'sales', 'products', 'categories', 'cashiers',
            'payments', 'profit', 'inventory', 'shifts', 'voids',
        ] as $report) {
            $this->get("/dashboard/reports/{$report}")
                ->assertOk();
        }
    }

    public function test_pdf_export_returns_a_pdf_document(): void
    {
        $this->recordSale();
        $this->actingAs($this->owner, 'web');

        $response = $this->get('/dashboard/reports/summary/export/pdf');

        $response->assertOk()->assertHeader('Content-Type', 'application/pdf');

        // dompdf returns a normal download response, not a streamed one.
        $this->assertStringStartsWith('%PDF', $response->getContent());
    }

    public function test_csv_export_contains_the_period_and_headings(): void
    {
        $this->recordSale();
        $this->actingAs($this->owner, 'web');

        $response = $this->get('/dashboard/reports/summary/export/csv');
        $response->assertOk();

        $csv = $response->streamedContent();

        $this->assertStringContainsString('Ringkasan Penjualan', $csv);
        $this->assertStringContainsString('Periode', $csv);
        $this->assertStringContainsString('Omzet', $csv);
    }

    public function test_invoice_and_shift_pdfs_render(): void
    {
        $sale = $this->recordSale();
        $this->actingAs($this->owner, 'web');

        $this->get("/dashboard/sales/{$sale->id}/invoice-pdf")
            ->assertOk()->assertHeader('Content-Type', 'application/pdf');

        $this->get("/dashboard/shifts/{$sale->shift_id}/pdf")
            ->assertOk()->assertHeader('Content-Type', 'application/pdf');
    }

    public function test_cashier_cannot_reach_reports(): void
    {
        $this->actingAs($this->kasir, 'pos');

        $this->get('/dashboard/reports')->assertRedirect(route('admin.login'));
    }
}
