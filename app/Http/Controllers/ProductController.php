<?php

namespace App\Http\Controllers;

use App\Enums\ProductCategory;
use App\Http\Requests\ProductRequest;
use App\Models\Product;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ProductController extends Controller
{
    public function index(Request $request): Response
    {
        $this->authorize('viewAny', Product::class);

        $products = Product::query()
            ->orderBy('sort_order')
            ->orderBy('category')
            ->orderBy('size_cm')
            ->get()
            ->map(fn (Product $p) => [
                'id' => $p->id,
                'name_ar' => $p->name_ar,
                'name_en' => $p->name_en,
                'category' => $p->category,
                'size_cm' => $p->size_cm,
                'unit' => $p->unit,
                'default_price' => (string) $p->default_price,
                'vat_rate' => $p->vat_rate !== null ? (string) $p->vat_rate : null,
                'active' => $p->active,
                'sort_order' => $p->sort_order,
            ]);

        return Inertia::render('products/index', [
            'products' => $products,
            'categories' => ProductCategory::values(),
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
}
