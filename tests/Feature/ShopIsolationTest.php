<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\Site;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ShopIsolationTest extends TestCase
{
    use RefreshDatabase;

    // 建立測試商店
    private function createTestSite(): Site
    {
        $seller = User::factory()->create([
            'role' => 'seller',
        ]);

        return Site::create([
            'owner_user_id' => $seller->id,
            'name' => '測試商店',
            'slug' => 'shop-' . uniqid(),
            'site_type' => 'ecommerce',
            'status' => 'draft',
            'settings' => [],
        ]);
    }

    // 建立測試商品
    private function createTestProduct(
        Site $site,
        bool $isActive = true
    ): Product {
        return Product::create([
            'site_id' => $site->id,
            'name' => '測試商品',
            'description' => '商店隔離測試',
            'price' => 100,
            'stock' => 10,
            'is_active' => $isActive,
        ]);
    }

    // 測試不能跨商店查看商品詳細資料
    public function test_cannot_view_product_from_another_site(): void
    {
        $siteA = $this->createTestSite();
        $siteB = $this->createTestSite();

        $productB = $this->createTestProduct($siteB);

        // 使用商店 A 的網址查看商店 B 的商品
        $this->get(route('shop.products.show', [
            'site' => $siteA->id,
            'product' => $productB->id,
        ]))->assertNotFound();
    }

    // 測試停用商品不能透過網址直接查看
    public function test_cannot_view_inactive_product(): void
    {
        $site = $this->createTestSite();

        $product = $this->createTestProduct($site, false);

        // 停用商品必須回傳 404
        $this->get(route('shop.products.show', [
            'site' => $site->id,
            'product' => $product->id,
        ]))->assertNotFound();
    }
}
