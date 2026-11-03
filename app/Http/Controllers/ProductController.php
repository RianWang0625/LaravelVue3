<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\Site;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;

class ProductController extends Controller
{
    // 顯示商品列表
    public function index()
    {
        $sites = Site::where('owner_user_id', Auth::id())
            ->get(['id', 'name']);

        $products = Product::whereIn('site_id', $sites->pluck('id'))
            ->with('variants')
            ->latest()
            ->get();

        return Inertia::render('Products/Index', [
            'products' => $products,
            'sites' => $sites,
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
            'image' => 'nullable|image|mimes:jpg,jpeg,png,webp|max:5120',
        ]);

        $site = Site::where('owner_user_id', Auth::id())
            ->findOrFail($validated['site_id']);

        $imagePath = null;

        // 上傳商品圖片
        if ($request->hasFile('image')) {
            $imagePath = $request->file('image')
                ->store('products', 'public');
        }

        try {
            $site->products()->create([
                'name' => $validated['name'],
                'description' => $validated['description'] ?? null,
                'price' => $validated['price'],
                'stock' => $validated['stock'],
                'image' => $imagePath,
                'is_active' => true,
            ]);
        } catch (\Throwable $e) {
            if ($imagePath) {
                Storage::disk('public')->delete($imagePath);
            }

            throw $e;
        }

        return redirect()->route('products.index')
            ->with('success', '商品新增成功');
    }

    // 修改商品
    public function update(Request $request, Product $product)
    {
        Site::where('owner_user_id', Auth::id())
            ->findOrFail($product->site_id);

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'price' => 'required|numeric|min:0',
            'stock' => 'required|integer|min:0',
            'is_active' => 'required|boolean',
            'image' => 'nullable|image|mimes:jpg,jpeg,png,webp|max:5120',
        ]);

        unset($validated['image']);

        $oldImagePath = $product->image;
        $newImagePath = null;

        // 更換商品圖片
        if ($request->hasFile('image')) {
            $newImagePath = $request->file('image')
                ->store('products', 'public');

            $validated['image'] = $newImagePath;
        }

        try {
            $product->update($validated);
        } catch (\Throwable $e) {
            if ($newImagePath) {
                Storage::disk('public')->delete($newImagePath);
            }

            throw $e;
        }

        // 刪除舊商品圖片
        if ($newImagePath && $oldImagePath && $oldImagePath !== $newImagePath) {
            Storage::disk('public')->delete($oldImagePath);
        }

        return redirect()->route('products.index')
            ->with('success', '商品修改成功');
    }

    // 刪除商品
    public function destroy(Product $product)
    {
        Site::where('owner_user_id', Auth::id())
            ->findOrFail($product->site_id);

        $imagePath = $product->image;

        $product->delete();

        // 刪除商品圖片
        if ($imagePath) {
            Storage::disk('public')->delete($imagePath);
        }

        return redirect()->route('products.index')
            ->with('success', '商品刪除成功');
    }
}
