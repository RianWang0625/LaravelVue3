<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\Site;
use Inertia\Inertia;

class ShopController extends Controller
{
    // 顯示指定網站的商品列表
    public function index(Site $site)
    {
        $products = Product::where('site_id', $site->id)
            ->where('is_active', true)
            ->with([
                'variants' => function ($query) {
                    $query->where('is_active', true);
                }
            ])
            ->latest()
            ->get();

        return Inertia::render('Shop/Index', [
            'site' => [
                'id' => $site->id,
                'name' => $site->name,
            ],
            'products' => $products,
        ]);
    }

    // 顯示指定網站的商品詳細資料
    public function show(Site $site, Product $product)
    {
        abort_unless(
            $product->site_id === $site->id
            && (bool) $product->is_active,
            404
        );

        $product->load([
            'variants' => function ($query) {
                $query->where('is_active', true);
            }
        ]);

        return Inertia::render('Shop/Show', [
            'site' => [
                'id' => $site->id,
                'name' => $site->name,
            ],
            'product' => $product,
        ]);
    }
}
