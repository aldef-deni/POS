<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\ReportService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * The reporting module: ten report types, all driven by a date range, all
 * exportable to PDF and CSV.
 */
class ReportController extends Controller
{
    /** Report key => [title, subtitle]. */
    public const REPORTS = [
        'summary' => ['Ringkasan Penjualan', 'Ikhtisar omzet, transaksi, dan laba pada periode terpilih'],
        'sales' => ['Detail Transaksi', 'Daftar seluruh transaksi beserta metode pembayaran'],
        'products' => ['Penjualan per Produk', 'Peringkat produk berdasarkan omzet, kuantitas, dan margin'],
        'categories' => ['Penjualan per Kategori', 'Kontribusi setiap kategori terhadap omzet'],
        'cashiers' => ['Kinerja Kasir', 'Perbandingan omzet dan rata-rata belanja tiap operator'],
        'payments' => ['Metode Pembayaran', 'Komposisi tunai, QRIS, kartu, dan transfer'],
        'profit' => ['Laba & Margin', 'Perkembangan laba kotor harian beserta persentase margin'],
        'inventory' => ['Nilai Persediaan', 'Posisi stok terkini beserta nilai modal dan nilai jual'],
        'shifts' => ['Rekap Shift Kasir', 'Riwayat buka/tutup laci beserta selisih kas'],
        'voids' => ['Transaksi Dibatalkan', 'Audit pembatalan transaksi beserta alasannya'],
    ];

    public function __construct(
        protected ReportService $reports,
    ) {}

    public function index(Request $request): View
    {
        [$from, $to] = $this->range($request);

        return view('dashboard.reports.index', [
            'from' => $from,
            'to' => $to,
            'reports' => self::REPORTS,
            'summary' => $this->reports->summary($from, $to),
        ]);
    }

    public function show(Request $request, string $report): View
    {
        [$from, $to] = $this->range($request);

        return view('dashboard.reports.show', $this->payload($request, $report, $from, $to) + [
            'cashiers' => User::orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function export(Request $request, string $report, string $format): Response|StreamedResponse
    {
        [$from, $to] = $this->range($request);

        $payload = $this->payload($request, $report, $from, $to);

        $filename = 'Laporan-'.ucfirst($report).'-'.$from.'_'.$to;

        if ($format === 'csv') {
            return $this->csv($payload, $filename);
        }

        $pdf = Pdf::loadView('print.report-pdf', $payload + ['tenant' => current_tenant()])
            ->setPaper('a4', in_array($report, ['sales', 'products', 'inventory', 'shifts'], true)
                ? 'landscape'
                : 'portrait');

        return $pdf->download($filename.'.pdf');
    }

    /** Build everything a report view or export needs. */
    protected function payload(Request $request, string $report, string $from, string $to): array
    {
        [$title, $subtitle] = self::REPORTS[$report] ?? ['Laporan', ''];

        $base = [
            'report' => $report,
            'title' => $title,
            'subtitle' => $subtitle,
            'from' => $from,
            'to' => $to,
            'summary' => $this->reports->summary($from, $to),
            'filters' => $request->only(['cashier', 'payment_method']),
            'generatedAt' => Carbon::now(),
            'generatedBy' => $request->user()?->name ?? 'Sistem',
        ];

        return $base + match ($report) {
            'summary' => [
                'series' => $this->reports->dailySeries($from, $to),
                'payments' => $this->reports->paymentBreakdown($from, $to),
                'topProducts' => $this->reports->productPerformance($from, $to, 10),
                'columns' => ['Tanggal', 'Transaksi', 'Omzet', 'Laba'],
                'rows' => $this->reports->dailySeries($from, $to)->map(fn ($row) => [
                    $row['label'],
                    (string) $row['transactions'],
                    money($row['revenue']),
                    money($row['profit']),
                ])->all(),
            ],

            'sales' => (function () use ($from, $to, $request) {
                $sales = $this->reports->salesList($from, $to, [
                    'user_id' => $request->query('cashier'),
                    'payment_method' => $request->query('payment_method'),
                ]);

                return [
                    'sales' => $sales,
                    'columns' => ['Waktu', 'No. Invoice', 'Kasir', 'Item', 'Pembayaran', 'Diskon', 'Total'],
                    'rows' => $sales->map(fn ($sale) => [
                        $sale->created_at->format('d/m/Y H:i'),
                        $sale->invoice_number,
                        $sale->user?->name ?? '—',
                        (string) $sale->item_count,
                        $sale->paymentLabel(),
                        money($sale->discount_amount),
                        money($sale->total),
                    ])->all(),
                ];
            })(),

            'products' => (function () use ($from, $to) {
                $rows = $this->reports->productPerformance($from, $to);

                return [
                    'data' => $rows,
                    'columns' => ['SKU', 'Produk', 'Kategori', 'Qty', 'Omzet', 'Modal', 'Laba', 'Margin'],
                    'rows' => $rows->map(fn ($r) => [
                        $r['sku'] ?? '—',
                        $r['name'],
                        $r['category'],
                        qty_label($r['qty']),
                        money($r['revenue']),
                        money($r['cost']),
                        money($r['profit']),
                        percent_label($r['margin_percent']),
                    ])->all(),
                ];
            })(),

            'categories' => (function () use ($from, $to) {
                $rows = $this->reports->categoryPerformance($from, $to);

                return [
                    'data' => $rows,
                    'columns' => ['Kategori', 'Qty', 'Omzet', 'Laba'],
                    'rows' => $rows->map(fn ($r) => [
                        $r['category'],
                        qty_label($r['qty']),
                        money($r['revenue']),
                        money($r['profit']),
                    ])->all(),
                ];
            })(),

            'cashiers' => (function () use ($from, $to) {
                $rows = $this->reports->cashierPerformance($from, $to);

                return [
                    'data' => $rows,
                    'columns' => ['Kasir', 'Peran', 'Transaksi', 'Omzet', 'Rata-rata', 'Diskon', 'Laba'],
                    'rows' => $rows->map(fn ($r) => [
                        $r['name'],
                        $r['role'],
                        (string) $r['transactions'],
                        money($r['revenue']),
                        money($r['average_basket']),
                        money($r['discount']),
                        money($r['profit']),
                    ])->all(),
                ];
            })(),

            'payments' => (function () use ($from, $to) {
                $rows = $this->reports->paymentBreakdown($from, $to);

                return [
                    'data' => $rows,
                    'columns' => ['Metode', 'Jumlah Transaksi', 'Nilai', 'Porsi'],
                    'rows' => $rows->map(fn ($r) => [
                        $r['label'],
                        (string) $r['count'],
                        money($r['amount']),
                        percent_label($r['share']),
                    ])->all(),
                ];
            })(),

            'profit' => (function () use ($from, $to) {
                $series = $this->reports->dailySeries($from, $to);

                return [
                    'series' => $series,
                    'data' => $this->reports->productPerformance($from, $to, 15),
                    'columns' => ['Tanggal', 'Transaksi', 'Omzet', 'Laba', 'Margin'],
                    'rows' => $series->map(fn ($r) => [
                        $r['label'],
                        (string) $r['transactions'],
                        money($r['revenue']),
                        money($r['profit']),
                        percent_label($r['revenue'] > 0 ? ($r['profit'] / $r['revenue']) * 100 : 0),
                    ])->all(),
                ];
            })(),

            'inventory' => (function () {
                $inventory = $this->reports->inventory();

                return [
                    'inventory' => $inventory,
                    'columns' => ['SKU', 'Produk', 'Kategori', 'Stok', 'Min', 'Modal/unit', 'Nilai Modal', 'Nilai Jual', 'Status'],
                    'rows' => $inventory['products']->map(fn ($r) => [
                        $r['sku'],
                        $r['name'],
                        $r['category'],
                        qty_label($r['stock']).' '.$r['unit'],
                        qty_label($r['min_stock']),
                        money($r['cost_price']),
                        money($r['value_cost']),
                        money($r['value_retail']),
                        ucfirst($r['status']),
                    ])->all(),
                ];
            })(),

            'shifts' => (function () use ($from, $to) {
                $shifts = $this->reports->shifts($from, $to);

                return [
                    'data' => $shifts,
                    'columns' => ['Buka', 'Tutup', 'Kasir', 'Modal Awal', 'Penjualan Tunai', 'Non-Tunai', 'Kas Seharusnya', 'Kas Dihitung', 'Selisih'],
                    'rows' => $shifts->map(fn ($s) => [
                        $s->opened_at->format('d/m/Y H:i'),
                        $s->closed_at?->format('d/m/Y H:i') ?? 'Masih terbuka',
                        $s->user?->name ?? '—',
                        money($s->opening_cash),
                        money($s->cash_sales),
                        money($s->non_cash_sales),
                        money($s->expected_cash),
                        $s->counted_cash !== null ? money($s->counted_cash) : '—',
                        money($s->cash_variance),
                    ])->all(),
                ];
            })(),

            'voids' => (function () use ($from, $to) {
                $voids = $this->reports->voids($from, $to);

                return [
                    'data' => $voids,
                    'columns' => ['Waktu Transaksi', 'No. Invoice', 'Kasir', 'Nilai', 'Dibatalkan Oleh', 'Waktu Void', 'Alasan'],
                    'rows' => $voids->map(fn ($s) => [
                        $s->created_at->format('d/m/Y H:i'),
                        $s->invoice_number,
                        $s->user?->name ?? '—',
                        money($s->total),
                        $s->voidedBy?->name ?? '—',
                        $s->voided_at?->format('d/m/Y H:i') ?? '—',
                        $s->void_reason ?? '—',
                    ])->all(),
                ];
            })(),

            default => ['columns' => [], 'rows' => []],
        };
    }

    /** Stream a CSV so large exports never buffer in memory. */
    protected function csv(array $payload, string $filename): StreamedResponse
    {
        $columns = $payload['columns'] ?? [];
        $rows = $payload['rows'] ?? [];

        return response()->streamDownload(function () use ($payload, $columns, $rows) {
            $out = fopen('php://output', 'w');

            // BOM so Excel opens UTF-8 correctly on Windows.
            fwrite($out, "\xEF\xBB\xBF");

            fputcsv($out, [$payload['title']]);
            fputcsv($out, ['Periode', $payload['from'].' s/d '.$payload['to']]);
            fputcsv($out, ['Dibuat', $payload['generatedAt']->format('d/m/Y H:i'), 'oleh', $payload['generatedBy']]);
            fputcsv($out, []);

            fputcsv($out, $columns);

            foreach ($rows as $row) {
                fputcsv($out, $row);
            }

            fclose($out);
        }, $filename.'.csv', [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    /** @return array{0:string,1:string} */
    protected function range(Request $request): array
    {
        $to = $request->date('to')?->toDateString() ?? Carbon::today()->toDateString();
        $from = $request->date('from')?->toDateString() ?? Carbon::today()->startOfMonth()->toDateString();

        // A backwards range is almost always a typo; swap rather than error.
        if (Carbon::parse($from)->gt(Carbon::parse($to))) {
            [$from, $to] = [$to, $from];
        }

        return [$from, $to];
    }
}
