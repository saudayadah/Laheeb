<?php

namespace App\Http\Controllers;

use App\Http\Requests\ProductRequest;
use App\Models\Product;
use App\Models\ProductCategory;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class ProductController extends Controller
{
    public function index(Request $request): Response
    {
        $this->authorize('viewAny', Product::class);

        $products = Product::query()
            ->with('category:id,name_ar,name_en,sort_order')
            ->get()
            ->sortBy(fn (Product $p) => [
                $p->category->sort_order ?? 9999,
                $p->category->name_ar ?? '',
                $p->sort_order,
                $p->size_cm ?? 0,
            ])
            ->values()
            ->map(fn (Product $p) => [
                'id' => $p->id,
                'name_ar' => $p->name_ar,
                'name_en' => $p->name_en,
                'product_category_id' => $p->product_category_id,
                'size_cm' => $p->size_cm,
                'unit' => $p->unit,
                'default_price' => (string) $p->default_price,
                'vat_rate' => $p->vat_rate !== null ? (string) $p->vat_rate : null,
                'active' => $p->active,
                'sort_order' => $p->sort_order,
            ]);

        return Inertia::render('products/index', [
            'products' => $products,
            'categories' => ProductCategory::query()
                ->orderBy('sort_order')
                ->orderBy('name_ar')
                ->get(['id', 'name_ar', 'name_en', 'active', 'sort_order']),
            'canManage' => $request->user()->can('products.manage'),
        ]);
    }

    public function store(ProductRequest $request): RedirectResponse
    {
        $this->authorize('create', Product::class);

        Product::create($request->validated());

        return redirect()->route('products.index')->with('success', __('common.saved'));
    }

    public function update(ProductRequest $request, Product $product): RedirectResponse
    {
        $this->authorize('update', $product);

        $product->update($request->validated());

        return redirect()->route('products.index')->with('success', __('common.saved'));
    }

    public function destroy(Product $product): RedirectResponse
    {
        $this->authorize('delete', $product);

        $product->delete();

        return redirect()->route('products.index')->with('success', __('common.deleted'));
    }

    public function storeCategory(Request $request): RedirectResponse
    {
        $this->authorize('create', Product::class);

        $data = $request->validate([
            'name_ar' => ['required', 'string', 'max:100', Rule::unique('product_categories', 'name_ar')],
            'name_en' => ['nullable', 'string', 'max:100'],
        ]);

        ProductCategory::create($data + [
            'sort_order' => (int) ProductCategory::max('sort_order') + 1,
        ]);

        return redirect()->route('products.index')->with('success', __('common.saved'));
    }

    public function updateCategory(Request $request, ProductCategory $category): RedirectResponse
    {
        $this->authorize('create', Product::class);

        $data = $request->validate([
            'name_ar' => ['required', 'string', 'max:100', Rule::unique('product_categories', 'name_ar')->ignore($category->id)],
            'name_en' => ['nullable', 'string', 'max:100'],
            'active' => ['boolean'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:65000'],
        ]);

        $category->update($data);

        return redirect()->route('products.index')->with('success', __('common.saved'));
    }
}
