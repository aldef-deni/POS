<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Models\Sale;
use App\Models\User;
use App\Services\CheckoutService;
use App\Services\CodeImageService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

class SaleController extends Controller
{
    public function __construct(
        protected CheckoutService $checkout,
        protected CodeImageService $codes,
    ) {}

    public function index(Request $request): View
    {
        $from = $request->date('from')?->toDateString() ?? Carbon::today()->subDays(29)->toDateString();
        $to = $request->date('to')?->toDateString() ?? Carbon::today()->toDateString();

        $sales = Sale::with(['user', 'customer', 'payments'])
            ->betweenDates($from, $to)
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->query('status')))
            ->when($request->filled('cashier'), fn ($q) => $q->where('user_id', $request->query('cashier')))
            ->when($request->filled('q'), fn ($q) => $q->where('invoice_number', 'like', '%'.$request->query('q').'%'))
            ->latest('created_at')
            ->paginate(25)
            ->withQueryString();

        return view('dashboard.sales.index', [
            'sales' => $sales,
            'from' => $from,
            'to' => $to,
            'cashiers' => User::orderBy('name')->get(['id', 'name']),
            'filters' => $request->only(['status', 'cashier', 'q']),
            'periodTotal' => (float) Sale::completed()->betweenDates($from, $to)->sum('total'),
        ]);
    }

    public function show(Sale $sale): View
    {
        $sale->load(['items.product', 'payments', 'user', 'customer', 'shift', 'voidedBy']);

        return view('dashboard.sales.show', compact('sale'));
    }

    /** Re-open the thermal receipt for reprinting. */
    public function receipt(Sale $sale): View
    {
        $sale->load(['items', 'payments', 'user', 'customer']);

        return view('print.receipt', [
            'sale' => $sale,
            'tenant' => $sale->tenant ?? current_tenant(),
            'qrDataUri' => $this->receiptQr($sale),
            'reprint' => true,
        ]);
    }

    /** A4 invoice as a downloadable PDF. */
    public function invoicePdf(Sale $sale): Response
    {
        $sale->load(['items', 'payments', 'user', 'customer']);

        $pdf = Pdf::loadView('print.invoice-pdf', [
            'sale' => $sale,
            'tenant' => current_tenant(),
            'qrDataUri' => $this->receiptQr($sale),
        ])->setPaper('a4');

        return $pdf->download('Invoice-'.$sale->invoice_number.'.pdf');
    }

    public function void(Request $request, Sale $sale): RedirectResponse
    {
        $data = $request->validate([
            'reason' => ['required', 'string', 'max:191'],
        ], [], ['reason' => 'alasan pembatalan']);

        $this->checkout->void($sale, $request->user(), $data['reason']);

        return back()->with('status', "Transaksi {$sale->invoice_number} dibatalkan dan stok dikembalikan.");
    }

    /**
     * QR printed on the receipt. It carries the invoice, timestamp and total
     * so a customer or auditor can verify the slip against the record.
     */
    protected function receiptQr(Sale $sale): string
    {
        $payload = implode('|', [
            $sale->invoice_number,
            $sale->created_at->format('Y-m-d H:i'),
            number_format((float) $sale->total, 2, '.', ''),
        ]);

        return $this->codes->qrPngDataUri($payload, 180);
    }
}
