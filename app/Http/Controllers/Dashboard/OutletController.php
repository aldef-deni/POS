<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\Outlet;
use App\Models\User;
use App\Services\ReportService;
use App\Support\OutletContext;
use App\Support\Tenancy;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Branch management, and the switch that decides which branch the rest of
 * the dashboard is looking at.
 */
class OutletController extends Controller
{
    public function __construct(
        protected ReportService $reports,
    ) {}

    public function index(Request $request): View
    {
        $from = $request->date('from')?->toDateString() ?? Carbon::today()->subDays(29)->toDateString();
        $to = $request->date('to')?->toDateString() ?? Carbon::today()->toDateString();

        // Compare branches across the whole chain regardless of which one is
        // currently selected in the topbar.
        $performance = app(OutletContext::class)->withoutScope(
            fn () => $this->reports->outletPerformance($from, $to)->keyBy('outlet_id')
        );

        return view('dashboard.outlets.index', [
            'outlets' => Outlet::withCount(['users' => fn ($q) => $q->where('is_active', true)])
                ->orderBy('sort_order')->orderBy('name')->get(),
            'performance' => $performance,
            'from' => $from,
            'to' => $to,
            'stockValues' => $this->stockValueByOutlet(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);

        $outlet = Outlet::create($data);

        $this->syncDefault($outlet, $request->boolean('is_default'));

        ActivityLog::record('outlet.create', "Menambah outlet {$outlet->name} ({$outlet->code})", $outlet);

        return back()->with('status', "Outlet {$outlet->name} ditambahkan.");
    }

    public function update(Request $request, Outlet $outlet): RedirectResponse
    {
        $outlet->update($this->validated($request, $outlet));

        $this->syncDefault($outlet, $request->boolean('is_default'));

        ActivityLog::record('outlet.update', "Mengubah outlet {$outlet->name}", $outlet);

        return back()->with('status', 'Data outlet diperbarui.');
    }

    public function destroy(Outlet $outlet): RedirectResponse
    {
        // Deleting a branch that traded would orphan its sales and stock
        // history, so it is deactivated instead.
        if ($outlet->sales()->withoutGlobalScope('outlet')->exists()) {
            $outlet->update(['is_active' => false, 'is_default' => false]);

            ActivityLog::record('outlet.deactivate', "Menonaktifkan outlet {$outlet->name}", $outlet);

            return back()->with('status',
                "Outlet {$outlet->name} dinonaktifkan karena sudah memiliki riwayat transaksi.");
        }

        if (Outlet::count() <= 1) {
            return back()->with('error', 'Minimal harus ada satu outlet.');
        }

        if ($outlet->users()->exists()) {
            return back()->with('error',
                'Masih ada pengguna yang ditugaskan di outlet ini. Pindahkan mereka terlebih dahulu.');
        }

        $name = $outlet->name;
        $outlet->delete();

        ActivityLog::record('outlet.delete', "Menghapus outlet {$name}");

        return back()->with('status', "Outlet {$name} dihapus.");
    }

    /**
     * Change which branch the dashboard is reading.
     *
     * Only an operator who is not pinned to a branch may do this; anyone
     * assigned to an outlet stays locked to it.
     */
    public function switch(Request $request): RedirectResponse
    {
        $user = $request->user();

        if ($user->outlet_id) {
            return back()->with('error',
                'Anda ditugaskan pada satu outlet dan tidak dapat berpindah.');
        }

        $data = $request->validate([
            'outlet_id' => ['nullable', 'integer', Rule::exists('outlets', 'id')
                ->where('tenant_id', app(Tenancy::class)->id())],
        ]);

        $key = \App\Http\Middleware\ResolveTenant::OUTLET_SESSION_KEY;

        if (empty($data['outlet_id'])) {
            $request->session()->forget($key);
            $label = 'Semua Outlet';
        } else {
            $request->session()->put($key, (int) $data['outlet_id']);
            $label = Outlet::find($data['outlet_id'])?->name ?? 'Outlet';
        }

        return back()->with('status', "Tampilan dialihkan ke {$label}.");
    }

    /** Stock valued at cost, per branch. */
    protected function stockValueByOutlet(): array
    {
        // The aggregate needs an alias to be plucked by name; a raw
        // expression as the pluck column silently yields nothing.
        return DB::table('outlet_stocks')
            ->join('products', 'products.id', '=', 'outlet_stocks.product_id')
            ->where('outlet_stocks.tenant_id', app(Tenancy::class)->id())
            ->groupBy('outlet_stocks.outlet_id')
            ->selectRaw('outlet_stocks.outlet_id as outlet_id,
                         SUM(outlet_stocks.stock * products.cost_price) as value')
            ->pluck('value', 'outlet_id')
            ->map(fn ($v) => (float) $v)
            ->all();
    }

    /** Exactly one branch may be the default at a time. */
    protected function syncDefault(Outlet $outlet, bool $wantsDefault): void
    {
        if (! $wantsDefault) {
            return;
        }

        Outlet::where('id', '!=', $outlet->id)->update(['is_default' => false]);
        $outlet->forceFill(['is_default' => true])->save();
    }

    protected function validated(Request $request, ?Outlet $outlet = null): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'code' => [
                'required', 'string', 'max:12', 'alpha_num',
                Rule::unique('outlets', 'code')
                    ->where('tenant_id', app(Tenancy::class)->id())
                    ->ignore($outlet?->id),
            ],
            'address' => ['nullable', 'string', 'max:500'],
            'city' => ['nullable', 'string', 'max:80'],
            'phone' => ['nullable', 'string', 'max:40'],
            'receipt_footer' => ['nullable', 'string', 'max:500'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
        ], [], [
            'name' => 'nama outlet',
            'code' => 'kode outlet',
        ]) + ['is_active' => $request->boolean('is_active', true)];
    }
}
