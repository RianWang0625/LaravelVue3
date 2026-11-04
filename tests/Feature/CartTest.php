<?php

namespace Tests\Feature;

use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class CartTest extends TestCase
{
    use RefreshDatabase;

    private int $siteAId;
    private int $siteBId;
    private Product $productA;
    private Product $productB;
    private int $variantId;

    // 建立兩個商店與測試商品
    protected function setUp(): void
    {
        parent::setUp();

        $this->siteAId = $this->createSite(
            '購物車測試商店 A',
            'cart-test-store-a'
        );

        $this->siteBId = $this->createSite(
            '購物車測試商店 B',
            'cart-test-store-b'
        );

        $this->productA = Product::create([
            'site_id' => $this->siteAId,
            'name' => '測試滑鼠',
            'description' => '購物車測試商品',
            'price' => 1290,
            'stock' => 20,
            'is_active' => true,
        ]);

        $variant = $this->productA->variants()->create([
            'sku' => 'CART-TEST-BLACK',
            'options' => ['顏色' => '黑色'],
            'price' => 1290,
            'stock' => 10,
            'is_active' => true,
        ]);

        $this->variantId = $variant->id;

        $this->productB = Product::create([
            'site_id' => $this->siteBId,
            'name' => '商店 B 測試商品',
            'description' => '沒有規格的商品',
            'price' => 500,
            'stock' => 5,
            'is_active' => true,
        ]);
    }

    // 建立測試網站
    private function createSite(string $name, string $slug): int
    {
        $owner = \App\Models\User::factory()->create();

        $data = [
            'owner_user_id' => $owner->id,
            'name' => $name,
            'slug' => $slug,
            'created_at' => now(),
            'updated_at' => now(),
        ];

        if (Schema::hasColumn('sites', 'type')) {
            $data['type'] = 'ecommerce';
        }

        if (Schema::hasColumn('sites', 'site_type')) {
            $data['site_type'] = 'ecommerce';
        }

        if (Schema::hasColumn('sites', 'status')) {
            $data['status'] = 'draft';
        }

        return DB::table('sites')->insertGetId($data);
    }

    // 確認規格商品可以加入購物車
    public function test_can_add_variant_to_cart(): void
    {
        $this->post("/shop/{$this->siteAId}/cart", [
            'product_id' => $this->productA->id,
            'variant_id' => $this->variantId,
            'quantity' => 2,
        ])->assertRedirect("/shop/{$this->siteAId}/cart");

        $key = "shop_cart_{$this->siteAId}";
        $itemKey = "p{$this->productA->id}_v{$this->variantId}";

        $this->assertSame(
            2,
            session("$key.$itemKey.quantity")
        );
    }

    // 確認同規格重複加入會累加數量
    public function test_adding_same_variant_increases_quantity(): void
    {
        $url = "/shop/{$this->siteAId}/cart";

        $data = [
            'product_id' => $this->productA->id,
            'variant_id' => $this->variantId,
            'quantity' => 2,
        ];

        $this->post($url, $data)->assertRedirect($url);
        $this->post($url, $data)->assertRedirect($url);

        $key = "shop_cart_{$this->siteAId}";
        $itemKey = "p{$this->productA->id}_v{$this->variantId}";

        $this->assertSame(
            4,
            session("$key.$itemKey.quantity")
        );
    }

    // 確認不能加入超過庫存的數量
    public function test_cannot_add_more_than_stock(): void
    {
        $this->post("/shop/{$this->siteAId}/cart", [
            'product_id' => $this->productA->id,
            'variant_id' => $this->variantId,
            'quantity' => 11,
        ])->assertSessionHasErrors('quantity');

        $this->assertEmpty(
            session("shop_cart_{$this->siteAId}", [])
        );
    }

    // 確認不能將其他商店的商品加入購物車
    public function test_cannot_add_product_from_another_store(): void
    {
        $this->post("/shop/{$this->siteBId}/cart", [
            'product_id' => $this->productA->id,
            'variant_id' => $this->variantId,
            'quantity' => 1,
        ])->assertNotFound();

        $this->assertEmpty(
            session("shop_cart_{$this->siteBId}", [])
        );
    }

    // 確認不同商店的購物車獨立
    public function test_carts_are_isolated_between_stores(): void
    {
        $this->post("/shop/{$this->siteAId}/cart", [
            'product_id' => $this->productA->id,
            'variant_id' => $this->variantId,
            'quantity' => 2,
        ])->assertRedirect();

        $this->post("/shop/{$this->siteBId}/cart", [
            'product_id' => $this->productB->id,
            'quantity' => 1,
        ])->assertRedirect();

        $cartA = session("shop_cart_{$this->siteAId}", []);
        $cartB = session("shop_cart_{$this->siteBId}", []);

        $this->assertCount(1, $cartA);
        $this->assertCount(1, $cartB);

        $this->assertSame(
            $this->productA->id,
            (int) array_values($cartA)[0]['product_id']
        );

        $this->assertSame(
            $this->productB->id,
            (int) array_values($cartB)[0]['product_id']
        );
    }

    // 確認購物車可以修改商品數量
    public function test_can_update_cart_quantity(): void
    {
        $this->post("/shop/{$this->siteAId}/cart", [
            'product_id' => $this->productA->id,
            'variant_id' => $this->variantId,
            'quantity' => 1,
        ])->assertRedirect();

        $itemKey = "p{$this->productA->id}_v{$this->variantId}";

        $this->put(
            "/shop/{$this->siteAId}/cart/{$itemKey}",
            ['quantity' => 3]
        )->assertRedirect();

        $this->assertSame(
            3,
            session("shop_cart_{$this->siteAId}.$itemKey.quantity")
        );
    }

    // 確認修改數量也不能超過庫存
    public function test_cannot_update_quantity_above_stock(): void
    {
        $this->post("/shop/{$this->siteAId}/cart", [
            'product_id' => $this->productA->id,
            'variant_id' => $this->variantId,
            'quantity' => 1,
        ])->assertRedirect();

        $itemKey = "p{$this->productA->id}_v{$this->variantId}";

        $this->put(
            "/shop/{$this->siteAId}/cart/{$itemKey}",
            ['quantity' => 11]
        )->assertSessionHasErrors('quantity');

        $this->assertSame(
            1,
            session("shop_cart_{$this->siteAId}.$itemKey.quantity")
        );
    }

    // 確認可以移除購物車商品
    public function test_can_remove_item_from_cart(): void
    {
        $this->post("/shop/{$this->siteAId}/cart", [
            'product_id' => $this->productA->id,
            'variant_id' => $this->variantId,
            'quantity' => 1,
        ])->assertRedirect();

        $itemKey = "p{$this->productA->id}_v{$this->variantId}";

        $this->delete(
            "/shop/{$this->siteAId}/cart/{$itemKey}"
        )->assertRedirect();

        $this->assertEmpty(
            session("shop_cart_{$this->siteAId}", [])
        );
    }

    // 確認加入購物車不會直接扣除商品庫存
    public function test_adding_to_cart_does_not_reduce_stock(): void
    {
        $this->post("/shop/{$this->siteAId}/cart", [
            'product_id' => $this->productA->id,
            'variant_id' => $this->variantId,
            'quantity' => 2,
        ])->assertRedirect();

        $this->assertDatabaseHas('product_variants', [
            'id' => $this->variantId,
            'stock' => 10,
        ]);
    }
}
