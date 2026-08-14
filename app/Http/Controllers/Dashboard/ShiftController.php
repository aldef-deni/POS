<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Models\Shift;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

class ShiftController extends Controller
{
    public function index(Request $request): View
    {
        $from = $request->date('from')?->toDateString() ?? Carbon::today()->subDays(29)->toDateString();
        $to = $request->date('to')?->toDateString() ?? Carbon::today()->toDateString();

        $shifts = Shift::with(['user', 'closedBy'])
            ->whereBetween('opened_at', [
                Carbon::parse($from)->startOfDay(),
                Carbon::parse($to)->endOfDay(),
            ])
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->query('status')))
            ->orderByDesc('opened_at')
            ->paginate(20)
            ->withQueryString();

        return view('dashboard.shifts.index', compact('shifts', 'from', 'to'));
    }

    public function show(Shift $shift): View
    {
        $shift->load(['user', 'closedBy']);

        return view('dashboard.shifts.show', [
            'shift' => $shift,
            'sales' => $shift->sales()->with(['user', 'payments'])->latest('created_at')->get(),
        ]);
    }

    public function pdf(Shift $shift): Response
    {
        $shift->load(['user', 'closedBy']);

        $pdf = Pdf::loadView('print.shift-pdf', [
            'shift' => $shift,
            'tenant' => current_tenant(),
            'sales' => $shift->sales()->with('payments')->orderBy('created_at')->get(),
        ])->setPaper('a4');

        return $pdf->download('Laporan-Shift-'.$shift->id.'.pdf');
    }
}
