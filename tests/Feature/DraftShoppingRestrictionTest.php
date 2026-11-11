<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\Site;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DraftShoppingRestrictionTest extends TestCase
{
    use RefreshDatabase;

    private Site $site;
    private Product $product;

    // 每個測試開始前建立草稿商店與商品
    protected function setUp(): void
    {
        parent::setUp();

        $seller = User::factory()->create([
            'role' => 'seller',
        ]);

        $this->site = Site::create([
            'owner_user_id' => $seller->id,
            'name' => '草稿測試商店',
            'slug' => 'draft-shopping-test',
            'site_type' => 'ecommerce',
            'status' => 'draft',
            'settings' => [],
        ]);

        $this->product = Product::create([
            'site_id' => $this->site->id,
            'name' => '測試商品',
            'description' => '草稿商店購物限制測試',
            'price' => 100,
            'stock' => 10,
            'is_active' => true,
        ]);
    }

    // 草稿商店不能查看購物車
    public function test_cannot_view_draft_cart(): void
    {
        $this->get(
            route('shop.cart.index', $this->site)
        )->assertNotFound();
    }

    // 草稿商店不能加入購物車
    public function test_cannot_add_product_to_draft_cart(): void
    {
        $this->post(
            route('shop.cart.store', $this->site),
            [
                'product_id' => $this->product->id,
                'quantity' => 1,
            ]
        )->assertNotFound();

        $this->assertEmpty(
            session('shop_cart_' . $this->site->id, [])
        );
    }

    // 草稿商店不能修改購物車數量
    public function test_cannot_update_draft_cart(): void
    {
        $key = 'p' . $this->product->id . '_v0';

        $this->withSession([
            'shop_cart_' . $this->site->id => [
                $key => [
                    'product_id' => $this->product->id,
                    'variant_id' => null,
                    'quantity' => 2,
                ],
            ],
        ]);

        $this->put(
            route('shop.cart.update', [
                'site' => $this->site->id,
                'key' => $key,
            ]),
            ['quantity' => 5]
        )->assertNotFound();

        $this->assertSame(
            2,
            session('shop_cart_' . $this->site->id)[$key]['quantity']
        );
    }

    // 草稿商店不能刪除購物車商品
    public function test_cannot_remove_draft_cart_item(): void
    {
        $key = 'p' . $this->product->id . '_v0';

        $this->withSession([
            'shop_cart_' . $this->site->id => [
                $key => [
                    'product_id' => $this->product->id,
                    'variant_id' => null,
                    'quantity' => 2,
                ],
            ],
        ]);

        $this->delete(
            route('shop.cart.destroy', [
                'site' => $this->site->id,
                'key' => $key,
            ])
        )->assertNotFound();

        $this->assertSame(
            2,
            session('shop_cart_' . $this->site->id)[$key]['quantity']
        );
    }

    // 草稿商店不能查看結帳頁面
    public function test_cannot_view_draft_checkout(): void
    {
        $this->get(
            route('shop.checkout.index', $this->site)
        )->assertNotFound();
    }

    // 草稿商店不能提交訂單
    public function test_cannot_checkout_draft_site(): void
    {
        $this->post(
            route('shop.checkout.store', $this->site),
            [
                'checkout_token' => str_repeat('t', 64),
                'customer_name' => '測試客戶',
                'customer_phone' => '0912345678',
                'recipient_name' => '測試收件人',
                'recipient_phone' => '0912345678',
                'shipping_address' => '台中市測試路1號',
            ]
        )->assertNotFound();

        $this->assertDatabaseCount('orders', 0);

        $this->assertDatabaseHas('products', [
            'id' => $this->product->id,
            'stock' => 10,
        ]);
    }

    // 草稿商店不能查看訂單成功頁面
    public function test_cannot_view_draft_order_success(): void
    {
        $this->get(
            route('shop.checkout.success', [
                'site' => $this->site->id,
                'order' => 999,
            ])
        )->assertNotFound();
    }
}
