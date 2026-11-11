<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class CheckoutTest extends TestCase
{
    use RefreshDatabase;

    private int $siteAId;
    private int $siteBId;
    private Product $productA;
    private int $variantId;

    // 建立測試網站與商品
    protected function setUp(): void
    {
        parent::setUp();

        $this->siteAId = $this->createSite(
            '結帳測試商店 A',
            'checkout-test-a'
        );

        $this->siteBId = $this->createSite(
            '結帳測試商店 B',
            'checkout-test-b'
        );

        $this->productA = Product::create([
            'site_id' => $this->siteAId,
            'name' => '測試滑鼠',
            'description' => '結帳功能測試',
            'price' => 1290,
            'stock' => 20,
            'is_active' => true,
        ]);

        $variant = $this->productA->variants()->create([
            'sku' => 'CHECKOUT-BLACK',
            'options' => ['顏色' => '黑色'],
            'price' => 1290,
            'stock' => 10,
            'is_active' => true,
        ]);

        $this->variantId = $variant->id;
    }

    // 建立測試商店
    private function createSite(string $name, string $slug): int
    {
        $owner = User::factory()->create();

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
            $data['status'] = 'published';
        }

        return DB::table('sites')->insertGetId($data);
    }

    // 建立測試購物車
    private function cart(int $quantity = 2): array
    {
        $key = "p{$this->productA->id}_v{$this->variantId}";

        return [
            $key => [
                'product_id' => $this->productA->id,
                'variant_id' => $this->variantId,
                'quantity' => $quantity,
            ],
        ];
    }

    // 建立測試結帳資料
    private function checkoutData(): array
    {
        return [
            'checkout_token' => str_repeat('t', 64),
            'customer_name' => '測試客戶',
            'customer_phone' => '0912345678',
            'customer_email' => 'checkout@example.com',
            'recipient_name' => '測試收件人',
            'recipient_phone' => '0912345678',
            'shipping_address' => '台中市測試路1號',
            'customer_note' => '自動化測試',
        ];
    }

    // 建立指定商店的結帳 Session
    private function checkoutSession(int $siteId, array $cart = []): array
    {
        return [
            "shop_checkout_token_{$siteId}" => str_repeat('t', 64),
            "shop_cart_{$siteId}" => $cart,
        ];
    }

    // 確認成功建立訂單與商品明細
    public function test_can_create_order(): void
    {
        $response = $this->withSession(
            $this->checkoutSession($this->siteAId, $this->cart())
        )->post(
            "/shop/{$this->siteAId}/checkout",
            $this->checkoutData()
        );

        $response->assertRedirect();
        $response->assertSessionHasNoErrors();

        $this->assertDatabaseCount('orders', 1);
        $this->assertDatabaseCount('order_items', 1);

        $order = Order::firstOrFail();

        $this->assertSame($this->siteAId, $order->site_id);
        $this->assertEquals(2580, $order->subtotal);
        $this->assertEquals(60, $order->shipping_fee);
        $this->assertEquals(2640, $order->total);
        $this->assertSame('pending', $order->payment_status);
        $this->assertSame(str_repeat('t', 64), $order->checkout_token);

        $this->assertDatabaseHas('order_items', [
            'order_id' => $order->id,
            'product_id' => $this->productA->id,
            'product_variant_id' => $this->variantId,
            'quantity' => 2,
            'unit_price' => '1290.00',
            'subtotal' => '2580.00',
        ]);

        $response->assertSessionMissing(
            "shop_cart_{$this->siteAId}"
        );
    }

    // 確認訂單成立後正確扣除規格庫存
    public function test_checkout_reduces_variant_stock(): void
    {
        $this->withSession(
            $this->checkoutSession($this->siteAId, $this->cart(2))
        )->post(
            "/shop/{$this->siteAId}/checkout",
            $this->checkoutData()
        )->assertRedirect();

        $this->assertDatabaseHas('product_variants', [
            'id' => $this->variantId,
            'stock' => 8,
        ]);
    }

    // 確認庫存不足不能建立訂單
    public function test_cannot_checkout_when_stock_is_insufficient(): void
    {
        $response = $this->withSession(
            $this->checkoutSession($this->siteAId, $this->cart(11))
        )->post(
            "/shop/{$this->siteAId}/checkout",
            $this->checkoutData()
        );

        $response->assertSessionHasErrors('cart');

        $this->assertDatabaseCount('orders', 0);
        $this->assertDatabaseCount('order_items', 0);

        $this->assertDatabaseHas('product_variants', [
            'id' => $this->variantId,
            'stock' => 10,
        ]);
    }

    // 確認不能在商店 B 購買商店 A 的商品
    public function test_cannot_checkout_product_from_another_store(): void
    {
        $response = $this->withSession(
            $this->checkoutSession($this->siteBId, $this->cart(1))
        )->post(
            "/shop/{$this->siteBId}/checkout",
            $this->checkoutData()
        );

        $response->assertSessionHasErrors('cart');

        $this->assertDatabaseCount('orders', 0);
        $this->assertDatabaseCount('order_items', 0);
    }

    // 確認空購物車不能建立訂單
    public function test_cannot_checkout_empty_cart(): void
    {
        $response = $this->withSession(
            $this->checkoutSession($this->siteAId)
        )->post(
            "/shop/{$this->siteAId}/checkout",
            $this->checkoutData()
        );

        $response->assertSessionHasErrors('cart');

        $this->assertDatabaseCount('orders', 0);
    }

    // 確認沒有訂單 Session 不能查看成功頁
    public function test_cannot_view_order_success_without_session(): void
    {
        $order = Order::create([
            'site_id' => $this->siteAId,
            'order_number' => 'ORD-TEST-00001',
            'customer_name' => '測試客戶',
            'customer_phone' => '0912345678',
            'customer_email' => 'test@example.com',
            'recipient_name' => '測試收件人',
            'recipient_phone' => '0912345678',
            'shipping_address' => '台中市測試路1號',
            'shipping_method' => 'home_delivery',
            'subtotal' => 1290,
            'shipping_fee' => 60,
            'discount_amount' => 0,
            'total' => 1350,
            'payment_method' => 'pending_selection',
            'payment_status' => 'pending',
            'order_status' => 'pending',
            'access_token' => str_repeat('a', 64),
        ]);

        $this->get(
            "/shop/{$this->siteAId}/orders/{$order->id}/success"
        )->assertNotFound();
    }

    // 相同 Token 重複提交只能建立一筆訂單
    public function test_duplicate_checkout_does_not_create_second_order(): void
    {
        $url = "/shop/{$this->siteAId}/checkout";

        $this->withSession(
            $this->checkoutSession($this->siteAId, $this->cart(2))
        );

        $first = $this->post($url, $this->checkoutData());
        $first->assertRedirect();
        $first->assertSessionHasNoErrors();

        // 確認第一次下單已儲存 Token
        $this->assertDatabaseHas('orders', [
            'site_id' => $this->siteAId,
            'checkout_token' => str_repeat('t', 64),
        ]);

        $second = $this->post($url, $this->checkoutData());
        $second->assertRedirect();
        $second->assertSessionHasNoErrors();

        $this->assertDatabaseCount('orders', 1);
        $this->assertDatabaseCount('order_items', 1);

        $order = Order::firstOrFail();

        $this->assertSame(str_repeat('t', 64), $order->checkout_token);

        $second->assertRedirect(
            "/shop/{$this->siteAId}/orders/{$order->id}/success"
        );

        $this->assertDatabaseHas('product_variants', [
            'id' => $this->variantId,
            'stock' => 8,
        ]);
    }

    // 錯誤 Token 不得建立訂單
    public function test_invalid_checkout_token_cannot_create_order(): void
    {
        $data = $this->checkoutData();
        $data['checkout_token'] = str_repeat('x', 64);

        $response = $this->withSession(
            $this->checkoutSession($this->siteAId, $this->cart(2))
        )->post(
            "/shop/{$this->siteAId}/checkout",
            $data
        );

        $response->assertSessionHasErrors('checkout_token');

        $this->assertDatabaseCount('orders', 0);
        $this->assertDatabaseCount('order_items', 0);

        $this->assertDatabaseHas('product_variants', [
            'id' => $this->variantId,
            'stock' => 10,
        ]);
    }

    // 測試買家竄改前端價格不會影響實際訂單金額
    public function test_checkout_ignores_tampered_price(): void
    {
        // 建立正常的結帳資料
        $data = $this->checkoutData();

        // 模擬買家在請求中竄改商品價格與訂單金額
        $data['price'] = 1;
        $data['subtotal'] = 1;
        $data['shipping_fee'] = 0;
        $data['total'] = 1;

        // 使用正常購物車購買兩件商品
        $response = $this->withSession(
            $this->checkoutSession(
                $this->siteAId,
                $this->cart(2)
            )
        )->post(
            "/shop/{$this->siteAId}/checkout",
            $data
        );

        // 確認訂單正常建立
        $response->assertRedirect();
        $response->assertSessionHasNoErrors();

        $this->assertDatabaseCount('orders', 1);

        $order = Order::firstOrFail();

        // 確認系統使用資料庫價格，而非前端竄改的價格
        $this->assertEquals(2580, $order->subtotal);
        $this->assertEquals(60, $order->shipping_fee);
        $this->assertEquals(2640, $order->total);

        // 確認訂單明細的單價也沒有被竄改
        $this->assertDatabaseHas('order_items', [
            'order_id' => $order->id,
            'product_id' => $this->productA->id,
            'product_variant_id' => $this->variantId,
            'unit_price' => '1290.00',
            'quantity' => 2,
            'subtotal' => '2580.00',
        ]);
    }

    // 測試結帳時不能使用其他商品的規格
    public function test_checkout_rejects_variant_from_another_product(): void
    {
        // 建立另一個商品
        $productB = Product::create([
            'site_id' => $this->siteAId,
            'name' => '測試鍵盤',
            'description' => '錯誤規格測試',
            'price' => 2000,
            'stock' => 10,
            'is_active' => true,
        ]);

        // 建立商品 B 專屬規格
        $variantB = $productB->variants()->create([
            'sku' => 'CHECKOUT-KEYBOARD-B',
            'options' => ['顏色' => '白色'],
            'price' => 2000,
            'stock' => 10,
            'is_active' => true,
        ]);

        // 購物車放入商品 A，但故意使用商品 B 的規格
        $cart = [
            "p{$this->productA->id}_v{$variantB->id}" => [
                'product_id' => $this->productA->id,
                'variant_id' => $variantB->id,
                'quantity' => 1,
            ],
        ];

        // 嘗試結帳
        $response = $this->withSession(
            $this->checkoutSession($this->siteAId, $cart)
        )->post(
            "/shop/{$this->siteAId}/checkout",
            $this->checkoutData()
        );

        // 必須拒絕建立訂單
        $response->assertSessionHasErrors('cart');

        // 確認沒有新增任何訂單與明細
        $this->assertDatabaseCount('orders', 0);
        $this->assertDatabaseCount('order_items', 0);

        // 確認商品 A 與商品 B 的規格庫存都沒有改變
        $this->assertDatabaseHas('product_variants', [
            'id' => $this->variantId,
            'stock' => 10,
        ]);

        $this->assertDatabaseHas('product_variants', [
            'id' => $variantB->id,
            'stock' => 10,
        ]);
    }

    // 測試多商品結帳失敗時，不會扣除任何商品庫存
    public function test_checkout_failure_does_not_reduce_any_stock(): void
    {
        // 建立第二個商品
        $productB = Product::create([
            'site_id' => $this->siteAId,
            'name' => '測試鍵盤',
            'description' => '交易回滾測試',
            'price' => 2000,
            'stock' => 1,
            'is_active' => true,
        ]);

        // 購物車包含兩件商品
        $cart = [
            "p{$this->productA->id}_v{$this->variantId}" => [
                'product_id' => $this->productA->id,
                'variant_id' => $this->variantId,
                'quantity' => 2,
            ],
            "p{$productB->id}_v0" => [
                'product_id' => $productB->id,
                'variant_id' => null,
                'quantity' => 2,
            ],
        ];

        // 第二個商品只有一件庫存，結帳應失敗
        $response = $this->withSession(
            $this->checkoutSession($this->siteAId, $cart)
        )->post(
            "/shop/{$this->siteAId}/checkout",
            $this->checkoutData()
        );

        // 確認結帳被拒絕
        $response->assertSessionHasErrors('cart');

        // 確認沒有建立訂單
        $this->assertDatabaseCount('orders', 0);
        $this->assertDatabaseCount('order_items', 0);

        // 第一個商品的規格庫存必須保持不變
        $this->assertDatabaseHas('product_variants', [
            'id' => $this->variantId,
            'stock' => 10,
        ]);

        // 第二個商品的庫存也必須保持不變
        $this->assertDatabaseHas('products', [
            'id' => $productB->id,
            'stock' => 1,
        ]);
    }



}
