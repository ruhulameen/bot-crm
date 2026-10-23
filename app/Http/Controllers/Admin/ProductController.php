<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ProductRequest;
use App\Models\Product;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ProductController extends Controller
{
    public function index(Request $request): View
    {
        $filters = $request->only(['q', 'active']);

        return view('admin.products.index', [
            'products' => Product::filter($filters)->withCount('licenses')->orderBy('name')->paginate(15)->withQueryString(),
            'filters' => $filters,
        ]);
    }

    public function create(): View
    {
        return view('admin.products.create', ['product' => new Product(['is_active' => true])]);
    }

    public function store(ProductRequest $request): RedirectResponse
    {
        Product::create($request->validated());

        return to_route('admin.products.index')->with('status', 'Product created.');
    }

    public function edit(Product $product): View
    {
        return view('admin.products.edit', ['product' => $product]);
    }

    public function update(ProductRequest $request, Product $product): RedirectResponse
    {
        $product->update($request->validated());

        return to_route('admin.products.index')->with('status', 'Product updated.');
    }

    public function destroy(Product $product): RedirectResponse
    {
        if ($product->licenses()->exists()) {
            return back()->with('error', 'This product still has licenses. Deactivate it instead of deleting.');
        }

        $product->delete();

        return to_route('admin.products.index')->with('status', 'Product deleted.');
    }
}
