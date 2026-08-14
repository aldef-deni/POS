<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\Sale;
use App\Models\Shift;
use App\Services\ReportService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __construct(
        protected ReportService $reports,
    ) {}

    public function index(Request $request): View
    {
        // Default view is the last 7 days, but the range picker at the top
        // of the page can widen it.
        $to = $request->date('to')?->toDateString() ?? Carbon::today()->toDateString();
        $from = $request->date('from')?->toDateString() ?? Carbon::today()->subDays(6)->toDateString();

        $today = Carbon::today()->toDateString();

        return view('dashboard.index', [
            'from' => $from,
            'to' => $to,
            'todaySummary' => $this->reports->summary($today, $today),
            'comparison' => $this->reports->comparison($from, $to),
            'series' => $this->reports->dailySeries($from, $to),
            'hourly' => $this->reports->hourlyDistribution($from, $to),
            'topProducts' => $this->reports->productPerformance($from, $to, 8),
            'categories' => $this->reports->categoryPerformance($from, $to),
            'payments' => $this->reports->paymentBreakdown($from, $to),
            'cashiers' => $this->reports->cashierPerformance($from, $to),
            'recentSales' => Sale::with(['user', 'customer'])
                ->latest('created_at')->limit(8)->get(),
            'lowStock' => Product::with('category')->lowStock()->active()
                ->orderBy('stock')->limit(8)->get(),
            'openShifts' => Shift::with('user')->open()->orderBy('opened_at')->get(),
            'productCount' => Product::active()->count(),
        ]);
    }
}
