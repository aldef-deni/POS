<?php

namespace App\Http\Controllers\Pos;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Customer;
use App\Models\HeldOrder;
use App\Models\Product;
use App\Models\SalePayment;
use App\Support\Tenancy;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PosController extends Controller
{
    /** Catalogue slice sent to the browser for instant local search. */
    protected const PRELOAD_LIMIT = 800;

    public function index(Request $request): View
    {
        $tenant = app(Tenancy::class)->get();
        $cashier = auth('pos')->user();
        $shift = $cashier->openShift();

        return view('pos.terminal', [
            'tenant' => $tenant,
            'cashier' => $cashier,
            'shift' => $shift,
            'categories' => Category::active()->orderBy('sort_order')->orderBy('name')->get(),
            'products' => $this->catalogue(),
            'paymentMethods' => SalePayment::methods(),
            'heldCount' => HeldOrder::where('user_id', $cashier->id)->count(),
            'todayCount' => $cashier->sales()
                ->whereDate('created_at', today())
                ->where('status', 'completed')
                ->count(),
        ]);
    }

    /** AJAX search, for catalogues larger than the preloaded slice. */
    public function products(Request $request): JsonResponse
    {
        return response()->json([
            'products' => $this->catalogue($request->query('q'), $request->query('category')),
        ]);
    }

    /**
     * Resolve a scanned barcode / QR payload to exactly one product.
     *
     * The hardware scanner types the code and presses Enter, so this has to
     * match on the barcode, the SKU, or the QR payload.
     */
    public function lookup(Request $request): JsonResponse
    {
        $code = trim((string) $request->query('code'));

        if ($code === '') {
            return response()->json(['found' => false, 'message' => 'Kode kosong.'], 422);
        }

        $product = Product::active()
            ->where(function ($query) use ($code) {
                $query->where('barcode_value', $code)
                    ->orWhere('sku', $code)
                    ->orWhere('qr_value', $code);
            })
            ->first();

        if (! $product) {
            return response()->json([
                'found' => false,
                'message' => "Produk dengan kode {$code} tidak ditemukan.",
            ], 404);
        }

        return response()->json([
            'found' => true,
            'product' => $this->transform($product),
        ]);
    }

    public function customers(Request $request): JsonResponse
    {
        $customers = Customer::search($request->query('q'))
            ->orderBy('name')
            ->limit(20)
            ->get(['id', 'name', 'phone', 'points', 'is_member']);

        return response()->json(['customers' => $customers]);
    }

    public function storeCustomer(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'phone' => ['nullable', 'string', 'max:40'],
        ], [], ['name' => 'nama pelanggan']);

        $customer = Customer::create($data + ['is_member' => true]);

        return response()->json(['customer' => $customer], 201);
    }

    /** @return array<int,array<string,mixed>> */
    protected function catalogue(?string $term = null, $categoryId = null): array
    {
        return Product::with('category')
            ->active()
            ->search($term)
            ->when($categoryId, fn ($q) => $q->where('category_id', $categoryId))
            ->orderByDesc('is_favorite')
            ->orderBy('name')
            ->limit(self::PRELOAD_LIMIT)
            ->get()
            ->map(fn (Product $p) => $this->transform($p))
            ->all();
    }

    /** @return array<string,mixed> */
    protected function transform(Product $product): array
    {
        return [
            'id' => $product->id,
            'sku' => $product->sku,
            'name' => $product->name,
            'unit' => $product->unit,
            'price' => (float) $product->price,
            'wholesale_price' => $product->wholesale_price !== null ? (float) $product->wholesale_price : null,
            'min_wholesale_qty' => (int) $product->min_wholesale_qty,
            'stock' => (float) $product->stock,
            'track_stock' => (bool) $product->track_stock,
            'tax_exempt' => (bool) $product->tax_exempt,
            'category_id' => $product->category_id,
            'category' => $product->category?->name,
            'color' => $product->category?->color ?? '#64748B',
            'image' => $product->imageUrl(),
            'barcode' => $product->barcode_value,
            'favorite' => (bool) $product->is_favorite,
        ];
    }
}
