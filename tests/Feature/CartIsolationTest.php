<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Site;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CartIsolationTest extends TestCase
{
    use RefreshDatabase;

    // 建立測試商店

    // 建立已正式發布的測試商店
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

            // 購物車測試需要已發布的商店
            'status' => 'published',

            'settings' => [],
        ]);
    }


    // 建立測試商品
    private function createTestProduct(Site $site): Product
    {
        return Product::create([
            'site_id' => $site->id,
            'name' => '測試商品',
            'description' => '購物車隔離測試',
            'price' => 100,
            'stock' => 10,
            'is_active' => true,
        ]);
    }

    // 測試不同商店的購物車 Session 互相隔離
    public function test_carts_are_isolated_between_sites(): void
    {
        $siteA = $this->createTestSite();
        $siteB = $this->createTestSite();

        $productA = $this->createTestProduct($siteA);

        // 將商店 A 的商品加入購物車
        $this->post(route('shop.cart.store', $siteA), [
            'product_id' => $productA->id,
            'quantity' => 2,
        ])->assertRedirect();

        // 確認商店 A 的購物車有商品
        $this->assertEquals(
            2,
            session('shop_cart_' . $siteA->id)['p' . $productA->id . '_v0']['quantity']
        );

        // 確認商店 B 的購物車沒有商品
        $this->assertEmpty(
            session('shop_cart_' . $siteB->id, [])
        );
    }

    // 測試不能將其他商店的商品加入購物車
    public function test_cannot_add_product_from_another_site(): void
    {
        $siteA = $this->createTestSite();
        $siteB = $this->createTestSite();

        $productB = $this->createTestProduct($siteB);

        // 使用商店 A 的網址加入商店 B 的商品
        $this->post(route('shop.cart.store', $siteA), [
            'product_id' => $productB->id,
            'quantity' => 1,
        ])->assertNotFound();

        // 確認商店 A 的購物車沒有被修改
        $this->assertEmpty(
            session('shop_cart_' . $siteA->id, [])
        );
    }

    // 測試不能使用其他商品的規格
    public function test_cannot_use_variant_from_another_product(): void
    {
        $site = $this->createTestSite();

        $productA = $this->createTestProduct($site);
        $productB = $this->createTestProduct($site);

        // 為商品 A 建立規格
        ProductVariant::create([
            'product_id' => $productA->id,
            'sku' => 'SKU-A',
            'options' => ['size' => 'L'],
            'price' => 100,
            'stock' => 10,
            'is_active' => true,
        ]);

        // 為商品 B 建立規格
        $variantB = ProductVariant::create([
            'product_id' => $productB->id,
            'sku' => 'SKU-B',
            'options' => ['size' => 'M'],
            'price' => 100,
            'stock' => 10,
            'is_active' => true,
        ]);

        // 嘗試將商品 B 的規格套用到商品 A
        $this->post(route('shop.cart.store', $site), [
            'product_id' => $productA->id,
            'variant_id' => $variantB->id,
            'quantity' => 1,
        ])
            ->assertRedirect()
            ->assertSessionHasErrors('variant_id');

        // 確認購物車沒有新增商品
        $this->assertEmpty(
            session('shop_cart_' . $site->id, [])
        );
    }

    // 測試加入數量不能超過商品庫存
    public function test_cannot_add_quantity_exceeding_stock(): void
    {
        $site = $this->createTestSite();
        $product = $this->createTestProduct($site);

        // 商品庫存只有十件，嘗試加入十一件
        $this->post(route('shop.cart.store', $site), [
            'product_id' => $product->id,
            'quantity' => 11,
        ])
            ->assertRedirect()
            ->assertSessionHasErrors('quantity');

        // 確認購物車沒有被修改
        $this->assertEmpty(
            session('shop_cart_' . $site->id, [])
        );
    }

    // 測試不能透過商店 B 修改商店 A 的購物車
    public function test_cannot_update_cart_from_another_site(): void
    {
        $siteA = $this->createTestSite();
        $siteB = $this->createTestSite();

        $productA = $this->createTestProduct($siteA);

        // 將商品加入商店 A 的購物車
        $this->post(route('shop.cart.store', $siteA), [
            'product_id' => $productA->id,
            'quantity' => 2,
        ])->assertRedirect();

        $key = 'p' . $productA->id . '_v0';

        // 嘗試透過商店 B 的網址修改商店 A 的購物車
        $this->put(route('shop.cart.update', [
            'site' => $siteB->id,
            'key' => $key,
        ]), [
            'quantity' => 5,
        ])->assertNotFound();

        // 確認商店 A 的購物車數量沒有改變
        $this->assertEquals(
            2,
            session('shop_cart_' . $siteA->id)[$key]['quantity']
        );
    }
}
