<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\Category;
use App\Support\Tenancy;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class CategoryController extends Controller
{
    public function index(): View
    {
        return view('dashboard.categories.index', [
            'categories' => Category::withCount('products')
                ->orderBy('sort_order')->orderBy('name')->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);

        $category = Category::create($data);

        ActivityLog::record('category.create', "Menambah kategori {$category->name}", $category);

        return back()->with('status', "Kategori {$category->name} ditambahkan.");
    }

    public function update(Request $request, Category $category): RedirectResponse
    {
        $category->update($this->validated($request, $category));

        ActivityLog::record('category.update', "Mengubah kategori {$category->name}", $category);

        return back()->with('status', 'Kategori diperbarui.');
    }

    public function destroy(Category $category): RedirectResponse
    {
        if ($category->products()->exists()) {
            return back()->with('error', 'Kategori masih dipakai produk. Pindahkan produknya terlebih dahulu.');
        }

        $name = $category->name;
        $category->delete();

        ActivityLog::record('category.delete', "Menghapus kategori {$name}", $category);

        return back()->with('status', "Kategori {$name} dihapus.");
    }

    protected function validated(Request $request, ?Category $category = null): array
    {
        return $request->validate([
            'name' => [
                'required', 'string', 'max:100',
                Rule::unique('categories', 'name')
                    ->where('tenant_id', app(Tenancy::class)->id())
                    ->whereNull('deleted_at')
                    ->ignore($category?->id),
            ],
            // Feeds the CATEGORY segment of generated product IDs.
            'code' => ['nullable', 'string', 'max:8', 'alpha_num'],
            'color' => ['nullable', 'string', 'max:16'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
        ], [], [
            'name' => 'nama kategori',
            'code' => 'kode kategori',
        ]) + ['is_active' => $request->boolean('is_active', true)];
    }
}
