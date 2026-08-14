<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\Product;
use App\Models\StockMovement;
use App\Services\StockService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class StockController extends Controller
{
    public function __construct(
        protected StockService $stock,
    ) {}

    public function index(Request $request): View
    {
        $movements = StockMovement::with(['product', 'user'])
            ->when($request->filled('product'), fn ($q) => $q->where('product_id', $request->query('product')))
            ->when($request->filled('type'), fn ($q) => $q->where('type', $request->query('type')))
            ->latest()
            ->paginate(25)
            ->withQueryString();

        return view('dashboard.stock.index', [
            'movements' => $movements,
            'products' => Product::active()->where('track_stock', true)->orderBy('name')->get(),
            'lowStock' => Product::with('category')->lowStock()->active()->orderBy('stock')->get(),
            'types' => StockMovement::types(),
            'filters' => $request->only(['product', 'type']),
        ]);
    }

    public function adjust(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'product_id' => ['required', 'integer', Rule::exists('products', 'id')],
            'direction' => ['required', Rule::in(['in', 'out'])],
            'qty' => ['required', 'numeric', 'gt:0'],
            'note' => ['nullable', 'string', 'max:191'],
        ], [], [
            'product_id' => 'produk',
            'qty' => 'jumlah',
        ]);

        $product = Product::findOrFail($data['product_id']);

        $signed = $data['direction'] === 'in' ? (float) $data['qty'] : -(float) $data['qty'];

        if ($data['direction'] === 'out' && (float) $product->stock < (float) $data['qty']) {
            return back()->with('error', "Stok {$product->name} tidak mencukupi untuk dikeluarkan.");
        }

        $this->stock->apply(
            $product,
            $signed,
            $data['direction'],
            null,
            $data['note'] ?? null,
            $request->user()->id,
        );

        ActivityLog::record(
            'stock.adjust',
            "Penyesuaian stok {$product->name}: ".($signed > 0 ? '+' : '').qty_label($signed),
            $product,
            ['qty' => $signed, 'note' => $data['note'] ?? null],
        );

        return back()->with('status', "Stok {$product->name} diperbarui menjadi ".qty_label($product->stock).' '.$product->unit.'.');
    }

    public function opname(Request $request): View
    {
        return view('dashboard.stock.opname', [
            'products' => Product::with('category')
                ->active()
                ->where('track_stock', true)
                ->search($request->query('q'))
                ->orderBy('name')
                ->get(),
            'filters' => $request->only('q'),
        ]);
    }

    /**
     * Record a physical count. Only rows whose counted figure differs from
     * the system figure produce a movement.
     */
    public function storeOpname(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'counted' => ['required', 'array'],
            'counted.*' => ['nullable', 'numeric', 'min:0'],
            'note' => ['nullable', 'string', 'max:191'],
        ]);

        $adjusted = 0;

        foreach ($data['counted'] as $productId => $countedQty) {
            if ($countedQty === null || $countedQty === '') {
                continue;
            }

            $product = Product::find($productId);

            if (! $product) {
                continue;
            }

            if (abs((float) $product->stock - (float) $countedQty) < 0.0001) {
                continue;
            }

            $this->stock->setAbsolute(
                $product,
                (float) $countedQty,
                $data['note'] ?? 'Stok opname',
                $request->user()->id,
            );

            $adjusted++;
        }

        ActivityLog::record('stock.opname', "Stok opname: {$adjusted} produk disesuaikan");

        return redirect()
            ->route('admin.stock.index')
            ->with('status', $adjusted > 0
                ? "Stok opname selesai. {$adjusted} produk disesuaikan."
                : 'Stok opname selesai. Tidak ada selisih yang ditemukan.');
    }
}
