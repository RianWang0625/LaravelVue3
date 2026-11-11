<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Auth;
use App\Models\Product;
use App\Models\Site;
use Inertia\Inertia;

class ShopController extends Controller
{


    // 確認網站已發布，或目前登入者是網站擁有者

    // 確認網站已發布，或目前登入者是網站擁有者
    private function ensureSiteAccessible(Site $site): void
    {
        // 已正式發布的網站允許所有訪客瀏覽
        if ($site->status === 'published') {
            return;
        }

        // 取得目前登入的會員
        /** @var User|null $user */
        $user = Auth::user();

        // 草稿網站只允許擁有者預覽
        if (
            $site->status === 'draft'
            && $user !== null
            && $user->id === (int) $site->owner_user_id
            && $user->isSeller()
        ) {
            return;
        }

        // 其他情況一律拒絕存取
        abort(404);
    }


    // 顯示指定網站的商品列表
    public function index(Site $site)
    {
        $this->ensureSiteAccessible($site);
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
        $this->ensureSiteAccessible($site);
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
