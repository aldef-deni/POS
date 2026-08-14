<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\Category;
use App\Services\SkuGenerator;
use App\Support\Tenancy;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Store profile, receipt layout, and the product-ID mechanism.
 */
class SettingsController extends Controller
{
    public function __construct(
        protected SkuGenerator $skus,
    ) {}

    public function index(): View
    {
        $tenant = app(Tenancy::class)->get();

        return view('dashboard.settings.index', [
            'tenant' => $tenant,
            'categories' => Category::active()->orderBy('name')->get(),
            'skuPreview' => $this->skus->preview($tenant),
            'skuPattern' => $this->skus->describe($tenant),
        ]);
    }

    public function updateStore(Request $request): RedirectResponse
    {
        $tenant = app(Tenancy::class)->get();

        $data = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'legal_name' => ['nullable', 'string', 'max:150'],
            'business_type' => ['nullable', 'string', 'max:60'],
            'address' => ['nullable', 'string', 'max:500'],
            'city' => ['nullable', 'string', 'max:80'],
            'phone' => ['nullable', 'string', 'max:40'],
            'email' => ['nullable', 'email', 'max:120'],
            'website' => ['nullable', 'string', 'max:120'],
            'tax_number' => ['nullable', 'string', 'max:40'],
            'currency_symbol' => ['required', 'string', 'max:8'],
            'tax_percent' => ['required', 'numeric', 'min:0', 'max:100'],
            'service_charge_percent' => ['required', 'numeric', 'min:0', 'max:100'],
            'rounding_mode' => ['required', Rule::in(['none', 'nearest_100', 'nearest_500', 'nearest_1000'])],
            'low_stock_threshold' => ['required', 'integer', 'min:0'],
            'logo' => ['nullable', 'image', 'max:2048'],
        ], [], ['name' => 'nama toko']);

        if ($request->hasFile('logo')) {
            if ($tenant->logo_path) {
                Storage::disk('public')->delete($tenant->logo_path);
            }

            $data['logo_path'] = $request->file('logo')->store('logos', 'public');
        }

        unset($data['logo']);

        $data['tax_enabled'] = $request->boolean('tax_enabled');
        $data['tax_inclusive'] = $request->boolean('tax_inclusive');
        $data['allow_negative_stock'] = $request->boolean('allow_negative_stock');

        $tenant->update($data);

        ActivityLog::record('settings.store', 'Memperbarui profil toko', $tenant);

        return back()->with('status', 'Profil toko diperbarui.');
    }

    public function updateReceipt(Request $request): RedirectResponse
    {
        $tenant = app(Tenancy::class)->get();

        $data = $request->validate([
            'receipt_paper' => ['required', Rule::in(['58mm', '80mm', 'a4'])],
            'receipt_header' => ['nullable', 'string', 'max:500'],
            'receipt_footer' => ['nullable', 'string', 'max:500'],
        ]);

        $data['receipt_show_qr'] = $request->boolean('receipt_show_qr');
        $data['receipt_show_logo'] = $request->boolean('receipt_show_logo');

        $tenant->update($data);

        ActivityLog::record('settings.receipt', 'Memperbarui format struk', $tenant);

        return back()->with('status', 'Format struk diperbarui.');
    }

    /**
     * The product-ID mechanism (requirement 8).
     *
     * Owners choose the prefix, whether the category code and a date segment
     * appear, how many digits the running number uses, and which barcode
     * symbology labels are printed in.
     */
    public function updateSku(Request $request): RedirectResponse
    {
        $tenant = app(Tenancy::class)->get();

        $data = $request->validate([
            'sku_prefix' => ['nullable', 'string', 'max:12', 'alpha_num'],
            'sku_separator' => ['required', 'string', 'max:2'],
            'sku_date_segment' => ['required', Rule::in(['none', 'yy', 'yymm', 'yymmdd'])],
            'sku_sequence_length' => ['required', 'integer', 'min:1', 'max:10'],
            'sku_next_number' => ['required', 'integer', 'min:1'],
            'barcode_type' => ['required', Rule::in(['C128', 'EAN13'])],
        ], [], [
            'sku_prefix' => 'prefix',
            'sku_sequence_length' => 'panjang nomor urut',
            'sku_next_number' => 'nomor urut berikutnya',
        ]);

        $data['sku_include_category'] = $request->boolean('sku_include_category');

        $tenant->update($data);

        ActivityLog::record(
            'settings.sku',
            'Memperbarui mekanisme ID produk: '.$this->skus->describe($tenant->fresh()),
            $tenant,
            $data,
        );

        return back()->with('status', 'Mekanisme ID produk diperbarui. Contoh ID berikutnya: '
            .$this->skus->preview($tenant->fresh()));
    }

    /** Live preview for the settings form, called as the Owner types. */
    public function previewSku(Request $request): JsonResponse
    {
        $tenant = app(Tenancy::class)->get();

        $overrides = $request->validate([
            'sku_prefix' => ['nullable', 'string', 'max:12'],
            'sku_separator' => ['nullable', 'string', 'max:2'],
            'sku_date_segment' => ['nullable', Rule::in(['none', 'yy', 'yymm', 'yymmdd'])],
            'sku_sequence_length' => ['nullable', 'integer', 'min:1', 'max:10'],
            'sku_next_number' => ['nullable', 'integer', 'min:1'],
            'category_id' => ['nullable', 'integer'],
        ]);

        $categoryId = $overrides['category_id'] ?? null;
        unset($overrides['category_id']);

        $overrides['sku_include_category'] = $request->boolean('sku_include_category');
        $overrides = array_filter($overrides, fn ($value) => $value !== null);

        $category = $categoryId ? Category::find($categoryId) : Category::active()->orderBy('name')->first();

        return response()->json([
            'preview' => $this->skus->preview($tenant, $category, $overrides),
            'pattern' => $this->skus->describe((clone $tenant)->forceFill($overrides)),
        ]);
    }
}
