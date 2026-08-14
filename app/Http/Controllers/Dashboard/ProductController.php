<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\Category;
use App\Models\Product;
use App\Services\CodeImageService;
use App\Services\SkuGenerator;
use App\Services\StockService;
use App\Support\Tenancy;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ProductController extends Controller
{
    public function __construct(
        protected SkuGenerator $skus,
        protected StockService $stock,
        protected CodeImageService $codes,
    ) {}

    public function index(Request $request): View
    {
        $products = Product::with('category')
            ->search($request->query('q'))
            ->when($request->filled('category'), fn ($q) => $q->where('category_id', $request->query('category')))
            ->when($request->query('status') === 'active', fn ($q) => $q->where('is_active', true))
            ->when($request->query('status') === 'inactive', fn ($q) => $q->where('is_active', false))
            ->when($request->query('status') === 'low', fn ($q) => $q->lowStock())
            ->orderBy($request->query('sort', 'name'), $request->query('dir', 'asc'))
            ->paginate(20)
            ->withQueryString();

        return view('dashboard.products.index', [
            'products' => $products,
            'categories' => Category::active()->orderBy('name')->get(),
            'filters' => $request->only(['q', 'category', 'status', 'sort', 'dir']),
        ]);
    }

    public function create(): View
    {
        $tenant = app(Tenancy::class)->get();

        return view('dashboard.products.form', [
            'product' => new Product(['unit' => 'pcs', 'is_active' => true, 'track_stock' => true]),
            'categories' => Category::active()->orderBy('name')->get(),
            // Show the operator the ID this product is about to receive.
            'skuPreview' => $this->skus->preview($tenant),
            'skuPattern' => $this->skus->describe($tenant),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);

        // Stock is set through the ledger, never written directly.
        $openingStock = (float) ($data['stock'] ?? 0);
        unset($data['stock']);

        $product = Product::create($data);

        if ($openingStock > 0) {
            $this->stock->apply(
                $product,
                $openingStock,
                'in',
                $product,
                'Stok awal saat produk dibuat',
                $request->user()->id,
            );
        }

        ActivityLog::record(
            'product.create',
            "Menambah produk {$product->name} ({$product->sku})",
            $product,
            ['sku' => $product->sku, 'barcode' => $product->barcode_value],
        );

        return redirect()
            ->route('admin.products.show', $product)
            ->with('status', "Produk berhasil dibuat dengan ID {$product->sku}. Barcode & QR sudah dibuat otomatis.");
    }

    public function show(Product $product): View
    {
        $product->load('category');

        // Fetched separately rather than as a limited eager load: that form
        // compiles to a window-function subquery MariaDB 10.x rejects.
        $movements = $product->stockMovements()
            ->with('user')
            ->latest()
            ->limit(20)
            ->get();

        return view('dashboard.products.show', [
            'product' => $product,
            'movements' => $movements,
            'barcodeSvg' => $this->codes->isRenderable((string) $product->barcode_value, $product->barcode_type)
                ? $this->codes->barcodeSvg((string) $product->barcode_value, $product->barcode_type, 2, 60)
                : null,
            'qrSvg' => $this->codes->qrSvg((string) ($product->qr_value ?: $product->sku), 200),
        ]);
    }

    public function edit(Product $product): View
    {
        return view('dashboard.products.form', [
            'product' => $product,
            'categories' => Category::active()->orderBy('name')->get(),
            'skuPreview' => $product->sku,
            'skuPattern' => $this->skus->describe(app(Tenancy::class)->get()),
        ]);
    }

    public function update(Request $request, Product $product): RedirectResponse
    {
        $data = $this->validated($request, $product);

        // Editing a product never silently rewrites the counted stock.
        unset($data['stock']);

        $product->update($data);

        ActivityLog::record(
            'product.update',
            "Mengubah produk {$product->name} ({$product->sku})",
            $product,
        );

        return redirect()
            ->route('admin.products.show', $product)
            ->with('status', 'Perubahan produk tersimpan.');
    }

    public function destroy(Product $product): RedirectResponse
    {
        $name = $product->name;
        $product->delete();

        ActivityLog::record('product.delete', "Menghapus produk {$name}", $product);

        return redirect()
            ->route('admin.products.index')
            ->with('status', "Produk {$name} dihapus.");
    }

    /**
     * Mint a fresh product ID and matching marks — useful when a label was
     * printed wrong or the ID format changed.
     */
    public function regenerateCode(Product $product): RedirectResponse
    {
        $tenant = app(Tenancy::class)->get();
        $sequence = (int) $tenant->sku_next_number;

        $sku = $this->skus->generate($tenant, $product->category);

        $product->update([
            'sku' => $sku,
            'barcode_type' => $tenant->barcode_type,
            'barcode_value' => $this->skus->barcodeValue($tenant, $sku, $sequence),
            'qr_value' => $sku,
        ]);

        ActivityLog::record(
            'product.regenerate_code',
            "Membuat ulang ID & barcode produk {$product->name}",
            $product,
            ['sku' => $sku],
        );

        return back()->with('status', "ID produk diperbarui menjadi {$sku}.");
    }

    /** Printable sheet of barcode labels. */
    public function labels(Request $request): View
    {
        $ids = array_filter((array) $request->query('ids', []));

        $products = Product::query()
            ->when($ids, fn ($q) => $q->whereIn('id', $ids))
            ->when(! $ids, fn ($q) => $q->active()->orderBy('name')->limit(40))
            ->get();

        $perProduct = max(1, min(50, (int) $request->query('copies', 1)));

        $labels = [];

        foreach ($products as $product) {
            for ($i = 0; $i < $perProduct; $i++) {
                $labels[] = $product;
            }
        }

        return view('dashboard.products.labels', [
            'labels' => $labels,
            'codes' => $this->codes,
            'showQr' => $request->boolean('qr', true),
            'showPrice' => $request->boolean('price', true),
        ]);
    }

    /** @return array<string,mixed> */
    protected function validated(Request $request, ?Product $product = null): array
    {
        $tenantId = app(Tenancy::class)->id();

        return $request->validate([
            'name' => ['required', 'string', 'max:191'],
            'category_id' => ['nullable', 'integer', Rule::exists('categories', 'id')->where('tenant_id', $tenantId)],
            'sku' => [
                'nullable', 'string', 'max:64',
                Rule::unique('products', 'sku')
                    ->where('tenant_id', $tenantId)
                    ->ignore($product?->id),
            ],
            'barcode_value' => [
                'nullable', 'string', 'max:64',
                Rule::unique('products', 'barcode_value')
                    ->where('tenant_id', $tenantId)
                    ->ignore($product?->id),
            ],
            'barcode_type' => ['nullable', Rule::in(['C128', 'EAN13'])],
            'description' => ['nullable', 'string', 'max:2000'],
            'unit' => ['required', 'string', 'max:20'],
            'cost_price' => ['required', 'numeric', 'min:0'],
            'price' => ['required', 'numeric', 'min:0'],
            'wholesale_price' => ['nullable', 'numeric', 'min:0'],
            'min_wholesale_qty' => ['nullable', 'integer', 'min:0'],
            'tax_exempt' => ['nullable', 'boolean'],
            'track_stock' => ['nullable', 'boolean'],
            'stock' => ['nullable', 'numeric', 'min:0'],
            'min_stock' => ['nullable', 'numeric', 'min:0'],
            'is_active' => ['nullable', 'boolean'],
            'is_favorite' => ['nullable', 'boolean'],
        ], [], [
            'name' => 'nama produk',
            'price' => 'harga jual',
            'cost_price' => 'harga modal',
        ]) + [
            // Unchecked boxes never reach the request at all.
            'tax_exempt' => $request->boolean('tax_exempt'),
            'track_stock' => $request->boolean('track_stock'),
            'is_active' => $request->boolean('is_active'),
            'is_favorite' => $request->boolean('is_favorite'),
        ];
    }
}
