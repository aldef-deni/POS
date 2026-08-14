<?php

namespace App\Http\Controllers\Pos;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\Shift;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Opening and closing the cash drawer.
 */
class PosShiftController extends Controller
{
    public function showOpen(): View|RedirectResponse
    {
        $cashier = auth('pos')->user();

        if ($shift = $cashier->openShift()) {
            return redirect()->route('pos.index');
        }

        return view('pos.shift-open', [
            'cashier' => $cashier,
            'lastShift' => $cashier->shifts()->latest('opened_at')->first(),
        ]);
    }

    public function open(Request $request): RedirectResponse
    {
        $cashier = auth('pos')->user();

        if ($cashier->openShift()) {
            return redirect()->route('pos.index');
        }

        $data = $request->validate([
            'opening_cash' => ['required', 'numeric', 'min:0'],
            'opening_note' => ['nullable', 'string', 'max:500'],
        ], [], ['opening_cash' => 'modal awal laci']);

        $shift = Shift::create([
            'user_id' => $cashier->id,
            'opened_at' => now(),
            'opening_cash' => $data['opening_cash'],
            'expected_cash' => $data['opening_cash'],
            'opening_note' => $data['opening_note'] ?? null,
            'status' => 'open',
        ]);

        ActivityLog::record(
            'shift.open',
            'Membuka shift dengan modal '.money($shift->opening_cash),
            $shift,
            [],
            $cashier,
            'pos',
        );

        return redirect()->route('pos.index')
            ->with('status', 'Shift dibuka. Selamat bertugas, '.$cashier->name.'!');
    }

    public function showClose(): View|RedirectResponse
    {
        $shift = auth('pos')->user()->openShift();

        if (! $shift) {
            return redirect()->route('pos.shift.open');
        }

        return view('pos.shift-close', [
            'shift' => $shift,
            'cashier' => auth('pos')->user(),
            'salesCount' => $shift->sales()->where('status', 'completed')->count(),
        ]);
    }

    public function close(Request $request): RedirectResponse
    {
        $cashier = auth('pos')->user();
        $shift = $cashier->openShift();

        if (! $shift) {
            return redirect()->route('pos.shift.open');
        }

        $data = $request->validate([
            'counted_cash' => ['required', 'numeric', 'min:0'],
            'closing_note' => ['nullable', 'string', 'max:500'],
        ], [], ['counted_cash' => 'jumlah kas dihitung']);

        $expected = $shift->expectedCashNow();
        $counted = (float) $data['counted_cash'];

        $shift->forceFill([
            'closed_at' => now(),
            'counted_cash' => $counted,
            'expected_cash' => $expected,
            // Positive means surplus in the drawer, negative means short.
            'cash_variance' => $counted - $expected,
            'closing_note' => $data['closing_note'] ?? null,
            'closed_by' => $cashier->id,
            'status' => 'closed',
        ])->save();

        ActivityLog::record(
            'shift.close',
            'Menutup shift. Selisih kas: '.money($shift->cash_variance),
            $shift,
            ['expected' => $expected, 'counted' => $counted],
            $cashier,
            'pos',
        );

        return redirect()->route('pos.shift.report', $shift)
            ->with('status', 'Shift ditutup.');
    }

    /** End-of-shift summary, printable from the terminal. */
    public function report(Shift $shift): View
    {
        $cashier = auth('pos')->user();

        // A cashier only ever sees their own drawer; a supervisor sees all.
        if ($shift->user_id !== $cashier->id && ! $cashier->hasPermission('shift.view.all')) {
            abort(403, 'Anda hanya dapat melihat laporan shift sendiri.');
        }

        return view('pos.shift-report', [
            'shift' => $shift->load('user'),
            'sales' => $shift->sales()->where('status', 'completed')->orderBy('created_at')->get(),
            'payments' => $shift->sales()
                ->where('status', 'completed')
                ->join('sale_payments', 'sale_payments.sale_id', '=', 'sales.id')
                ->selectRaw('sale_payments.method, SUM(sale_payments.amount) as amount, COUNT(*) as count')
                ->groupBy('sale_payments.method')
                ->get(),
        ]);
    }
}
