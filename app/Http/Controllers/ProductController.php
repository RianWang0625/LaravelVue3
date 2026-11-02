<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\Site;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

class ProductController extends Controller
{
    // 顯示商品列表
    public function index()
    {
        $sites = Site::where('owner_user_id', Auth::id())
            ->get(['id', 'name']);

        $products = Product::whereIn('site_id', $sites->pluck('id'))
            ->latest()
            ->get();

        return Inertia::render('Products/Index', [
            'products' => $products,
            'sites' => $sites
        ]);
    }

    // 新增商品
    public function store(Request $request)
    {
        $validated = $request->validate([
            'site_id' => 'required|integer',
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'price' => 'required|numeric|min:0',
            'stock' => 'required|integer|min:0',
        ]);

        $site = Site::where('owner_user_id', Auth::id())
            ->findOrFail($validated['site_id']);

        $site->products()->create([
            'name' => $validated['name'],
            'description' => $validated['description'] ?? null,
            'price' => $validated['price'],
            'stock' => $validated['stock'],
            'is_active' => true,
        ]);

        return redirect()->route('products.index')
            ->with('success', '商品新增成功');
    }
}
