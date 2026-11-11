<?php

namespace Tests\Feature;

use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Order;
use App\Models\Site;
use Illuminate\Support\Str;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrderManagementTest extends TestCase
{
    use RefreshDatabase;

    // 測試 Buyer 無法查看商家訂單列表
    public function test_buyer_cannot_view_order_list(): void
    {
        // 建立 Buyer 帳號
        $buyer = User::factory()->create([
            'role' => 'buyer',
        ]);

        // 模擬 Buyer 登入並嘗試查看訂單管理頁面
        $this->actingAs($buyer)
            ->get(route('seller.orders.index'))
            ->assertForbidden();
    }

    // 測試 Seller 可以查看自己的訂單管理列表
    public function test_seller_can_view_order_list(): void
    {
        // 建立 Seller 帳號
        $seller = User::factory()->create([
            'role' => 'seller',
        ]);

        // 驗證 Seller 可以進入訂單管理頁面
        $this->actingAs($seller)
            ->get(route('seller.orders.index'))
            ->assertOk();
    }

    // 建立指定商家的測試訂單
    private function createTestOrder(User $seller): Order
    {
        // 建立商家所屬的已發布測試網站
        $site = Site::create([
            'owner_user_id' => $seller->id,
            'name' => '測試商店',
            'slug' => 'test-shop-' . Str::random(12),
            'site_type' => 'ecommerce',
            'status' => 'published',
            'settings' => [],
        ]);


        // 建立符合資料庫必填欄位的訂單
        return Order::create([
            'site_id' => $site->id,
            'order_number' => 'TEST-' . Str::upper(Str::random(16)),
            'customer_name' => '測試買家',
            'customer_phone' => '0912345678',
            'customer_email' => 'buyer@example.com',
            'recipient_name' => '測試收件人',
            'recipient_phone' => '0912345678',
            'shipping_address' => '台中市測試路1號',
            'shipping_method' => 'home_delivery',
            'subtotal' => 1000,
            'shipping_fee' => 0,
            'discount_amount' => 0,
            'total' => 1000,
            'payment_method' => 'pending_selection',
            'payment_status' => 'pending',
            'order_status' => 'pending',
            'access_token' => Str::random(64),
        ]);
    }

    // 測試 Seller 無法查看其他商家的訂單
    public function test_seller_cannot_view_other_seller_order(): void
    {
        $sellerA = User::factory()->create(['role' => 'seller']);
        $sellerB = User::factory()->create(['role' => 'seller']);

        $order = $this->createTestOrder($sellerB);

        $this->actingAs($sellerA)
            ->get(route('seller.orders.show', $order))
            ->assertNotFound();
    }

    // 測試 Seller 無法處理其他商家的訂單
    public function test_seller_cannot_process_other_seller_order(): void
    {
        $sellerA = User::factory()->create(['role' => 'seller']);
        $sellerB = User::factory()->create(['role' => 'seller']);

        $order = $this->createTestOrder($sellerB);

        $this->actingAs($sellerA)
            ->patch(route('seller.orders.process', $order))
            ->assertNotFound();

        // 確認訂單仍維持待處理狀態
        $this->assertDatabaseHas('orders', [
            'id' => $order->id,
            'order_status' => 'pending',
        ]);
    }

    // 測試 Seller 無法出貨其他商家的訂單
    public function test_seller_cannot_ship_other_seller_order(): void
    {
        $sellerA = User::factory()->create(['role' => 'seller']);
        $sellerB = User::factory()->create(['role' => 'seller']);

        $order = $this->createTestOrder($sellerB);

        $this->actingAs($sellerA)
            ->patch(route('seller.orders.ship', $order), [
                'shipping_company' => 'TEST',
                'tracking_number' => 'HACKED123',
            ])
            ->assertNotFound();

        // 確認訂單狀態與出貨資訊未被修改
        $this->assertDatabaseHas('orders', [
            'id' => $order->id,
            'order_status' => 'pending',
            'tracking_number' => null,
            'shipping_company' => null,
        ]);
    }

    // 測試 Buyer 無法查看或操作商家訂單
    public function test_buyer_cannot_manage_seller_order(): void
    {
        // 建立商家與買家帳號
        $seller = User::factory()->create(['role' => 'seller']);
        $buyer = User::factory()->create(['role' => 'buyer']);

        // 建立商家的測試訂單
        $order = $this->createTestOrder($seller);

        // 驗證 Buyer 無法查看訂單明細
        $this->actingAs($buyer)
            ->get(route('seller.orders.show', $order))
            ->assertForbidden();

        // 驗證 Buyer 無法將訂單改為處理中
        $this->actingAs($buyer)
            ->patch(route('seller.orders.process', $order))
            ->assertForbidden();

        // 驗證 Buyer 無法模擬付款
        $this->actingAs($buyer)
            ->patch(route('seller.orders.simulate-payment', $order))
            ->assertForbidden();

        // 驗證 Buyer 無法登記出貨
        $this->actingAs($buyer)
            ->patch(route('seller.orders.ship', $order), [
                'shipping_company' => 'TEST',
                'tracking_number' => 'HACKED123',
            ])
            ->assertForbidden();

        // 驗證 Buyer 無法完成訂單
        $this->actingAs($buyer)
            ->patch(route('seller.orders.complete', $order))
            ->assertForbidden();

        // 驗證 Buyer 無法取消訂單
        $this->actingAs($buyer)
            ->patch(route('seller.orders.cancel', $order))
            ->assertForbidden();

        // 確認訂單狀態與付款資訊未被修改
        $this->assertDatabaseHas('orders', [
            'id' => $order->id,
            'order_status' => 'pending',
            'payment_status' => 'pending',
            'tracking_number' => null,
        ]);
    }


    // 測試 Seller 可以正常完成自己的訂單
    public function test_seller_can_complete_order_workflow(): void
    {
        // 建立 Seller 與測試訂單
        $seller = User::factory()->create([
            'role' => 'seller',
        ]);

        $order = $this->createTestOrder($seller);

        // 模擬 Seller 登入
        $this->actingAs($seller);

        // 第一步：將待處理訂單改為處理中
        $this->patch(
            route('seller.orders.process', $order)
        )->assertRedirect();

        $this->assertDatabaseHas('orders', [
            'id' => $order->id,
            'order_status' => 'processing',
        ]);

        // 第二步：模擬付款成功
        $this->patch(
            route('seller.orders.simulate-payment', $order)
        )->assertRedirect();

        $this->assertDatabaseHas('orders', [
            'id' => $order->id,
            'payment_status' => 'paid',
        ]);

        // 第三步：登記出貨
        $this->patch(
            route('seller.orders.ship', $order),
            [
                'shipping_company' => '黑貓宅急便',
                'tracking_number' => 'TEST123456',
            ]
        )->assertRedirect();

        $this->assertDatabaseHas('orders', [
            'id' => $order->id,
            'order_status' => 'shipped',
            'shipping_company' => '黑貓宅急便',
            'tracking_number' => 'TEST123456',
        ]);

        // 第四步：完成訂單
        $this->patch(
            route('seller.orders.complete', $order)
        )->assertRedirect();

        $this->assertDatabaseHas('orders', [
            'id' => $order->id,
            'order_status' => 'completed',
            'payment_status' => 'paid',
        ]);
    }


    // 測試取消訂單後恢復一般商品庫存
    public function test_cancel_order_restores_product_stock(): void
    {
        // 建立商家與訂單
        $seller = User::factory()->create(['role' => 'seller']);
        $order = $this->createTestOrder($seller);

        // 建立已扣除購買數量的商品
        $product = Product::create([
            'site_id' => $order->site_id,
            'name' => '測試商品',
            'description' => '測試取消訂單',
            'price' => 100,
            'stock' => 7,
            'is_active' => true,
        ]);

        // 建立購買三件商品的訂單明細
        OrderItem::create([
            'order_id' => $order->id,
            'product_id' => $product->id,
            'product_variant_id' => null,
            'product_name' => '測試商品',
            'unit_price' => 100,
            'quantity' => 3,
            'subtotal' => 300,
        ]);

        // 商家取消訂單
        $this->actingAs($seller)
            ->patch(route('seller.orders.cancel', $order))
            ->assertRedirect();

        // 確認訂單已取消
        $this->assertDatabaseHas('orders', [
            'id' => $order->id,
            'order_status' => 'cancelled',
        ]);

        // 確認商品庫存從 7 恢復到 10
        $this->assertDatabaseHas('products', [
            'id' => $product->id,
            'stock' => 10,
        ]);
    }

    // 測試取消訂單後恢復商品規格庫存
    public function test_cancel_order_restores_variant_stock(): void
    {
        // 建立商家與訂單
        $seller = User::factory()->create(['role' => 'seller']);
        $order = $this->createTestOrder($seller);

        // 建立商品
        $product = Product::create([
            'site_id' => $order->site_id,
            'name' => '測試衣服',
            'description' => '多規格測試商品',
            'price' => 500,
            'stock' => 0,
            'is_active' => true,
        ]);

        // 建立已扣除購買數量的商品規格
        $variant = ProductVariant::create([
            'product_id' => $product->id,
            'sku' => 'TEST-BLACK-L',
            'options' => ['color' => 'black', 'size' => 'L'],
            'price' => 500,
            'stock' => 5,
            'is_active' => true,
        ]);

        // 建立購買兩件規格商品的訂單明細
        OrderItem::create([
            'order_id' => $order->id,
            'product_id' => $product->id,
            'product_variant_id' => $variant->id,
            'product_name' => '測試衣服',
            'variant_options' => ['color' => 'black', 'size' => 'L'],
            'unit_price' => 500,
            'quantity' => 2,
            'subtotal' => 1000,
        ]);

        // 商家取消訂單
        $this->actingAs($seller)
            ->patch(route('seller.orders.cancel', $order))
            ->assertRedirect();

        // 確認規格庫存從 5 恢復到 7
        $this->assertDatabaseHas('product_variants', [
            'id' => $variant->id,
            'stock' => 7,
        ]);

        // 確認訂單已取消
        $this->assertDatabaseHas('orders', [
            'id' => $order->id,
            'order_status' => 'cancelled',
        ]);
    }

    // 測試重複取消訂單不會重複恢復庫存
    public function test_cancel_order_does_not_restore_stock_twice(): void
    {
        // 建立商家與訂單
        $seller = User::factory()->create(['role' => 'seller']);
        $order = $this->createTestOrder($seller);

        // 建立商品
        $product = Product::create([
            'site_id' => $order->site_id,
            'name' => '防止重複取消商品',
            'price' => 100,
            'stock' => 8,
            'is_active' => true,
        ]);

        // 建立訂單明細
        OrderItem::create([
            'order_id' => $order->id,
            'product_id' => $product->id,
            'product_name' => '防止重複取消商品',
            'unit_price' => 100,
            'quantity' => 2,
            'subtotal' => 200,
        ]);

        // 第一次取消訂單
        $this->actingAs($seller)
            ->patch(route('seller.orders.cancel', $order))
            ->assertRedirect();

        // 確認庫存恢復到 10
        $this->assertDatabaseHas('products', [
            'id' => $product->id,
            'stock' => 10,
        ]);

        // 第二次嘗試取消同一張訂單
        $this->patch(route('seller.orders.cancel', $order));

        // 確認庫存沒有再次增加
        $this->assertDatabaseHas('products', [
            'id' => $product->id,
            'stock' => 10,
        ]);

        // 確認訂單仍維持取消狀態
        $this->assertDatabaseHas('orders', [
            'id' => $order->id,
            'order_status' => 'cancelled',
        ]);
    }


    // 測試已付款訂單不能直接取消
    public function test_paid_order_cannot_be_cancelled(): void
    {
        // 建立商家與測試訂單
        $seller = User::factory()->create(['role' => 'seller']);
        $order = $this->createTestOrder($seller);

        // 將訂單設定為已付款
        $order->update([
            'payment_status' => 'paid',
        ]);

        // 嘗試取消已付款訂單
        $this->actingAs($seller)
            ->from(route('seller.orders.show', $order))
            ->patch(route('seller.orders.cancel', $order))
            ->assertRedirect()
            ->assertSessionHasErrors('payment_status');

        // 確認訂單狀態沒有被修改
        $this->assertDatabaseHas('orders', [
            'id' => $order->id,
            'order_status' => 'pending',
            'payment_status' => 'paid',
        ]);
    }

    // 測試未付款訂單不能出貨
    public function test_unpaid_order_cannot_be_shipped(): void
    {
        // 建立商家與測試訂單
        $seller = User::factory()->create(['role' => 'seller']);
        $order = $this->createTestOrder($seller);

        // 設定訂單為處理中但尚未付款
        $order->update([
            'order_status' => 'processing',
            'payment_status' => 'pending',
        ]);

        // 嘗試將未付款訂單出貨
        $this->actingAs($seller)
            ->from(route('seller.orders.show', $order))
            ->patch(route('seller.orders.ship', $order), [
                'shipping_company' => '黑貓宅急便',
                'tracking_number' => 'TEST123456',
            ])
            ->assertRedirect()
            ->assertSessionHasErrors('payment_status');

        // 確認訂單沒有被出貨
        $this->assertDatabaseHas('orders', [
            'id' => $order->id,
            'order_status' => 'processing',
            'payment_status' => 'pending',
            'shipping_company' => null,
            'tracking_number' => null,
            'shipped_at' => null,
        ]);
    }

    // 測試未出貨訂單不能直接標記完成
    public function test_unshipped_order_cannot_be_completed(): void
    {
        // 建立商家與訂單
        $seller = User::factory()->create(['role' => 'seller']);
        $order = $this->createTestOrder($seller);

        // 設定訂單已付款，但尚未出貨
        $order->update([
            'order_status' => 'processing',
            'payment_status' => 'paid',
        ]);

        // 嘗試直接完成訂單
        $this->actingAs($seller)
            ->from(route('seller.orders.show', $order))
            ->patch(route('seller.orders.complete', $order))
            ->assertRedirect()
            ->assertSessionHasErrors('order_status');

        // 確認訂單仍維持處理中
        $this->assertDatabaseHas('orders', [
            'id' => $order->id,
            'order_status' => 'processing',
            'payment_status' => 'paid',
        ]);
    }

    // 測試已完成訂單不能重新處理
    public function test_completed_order_cannot_be_processed_again(): void
    {
        // 建立商家與訂單
        $seller = User::factory()->create(['role' => 'seller']);
        $order = $this->createTestOrder($seller);

        // 設定訂單已完成
        $order->update([
            'order_status' => 'completed',
            'payment_status' => 'paid',
        ]);

        // 嘗試重新處理已完成訂單
        $this->actingAs($seller)
            ->from(route('seller.orders.show', $order))
            ->patch(route('seller.orders.process', $order))
            ->assertRedirect()
            ->assertSessionHasErrors('order_status');

        // 確認訂單狀態沒有被修改
        $this->assertDatabaseHas('orders', [
            'id' => $order->id,
            'order_status' => 'completed',
            'payment_status' => 'paid',
        ]);
    }

    // 測試已出貨訂單不能重複出貨
    public function test_shipped_order_cannot_be_shipped_again(): void
    {
        // 建立商家與訂單
        $seller = User::factory()->create(['role' => 'seller']);
        $order = $this->createTestOrder($seller);

        // 設定訂單已出貨
        $order->update([
            'order_status' => 'shipped',
            'payment_status' => 'paid',
            'shipping_company' => '黑貓宅急便',
            'tracking_number' => 'ORIGINAL123',
            'shipped_at' => now(),
        ]);

        // 嘗試重複出貨並修改物流資訊
        $this->actingAs($seller)
            ->from(route('seller.orders.show', $order))
            ->patch(route('seller.orders.ship', $order), [
                'shipping_company' => '其他物流',
                'tracking_number' => 'HACKED123',
            ])
            ->assertRedirect()
            ->assertSessionHasErrors('order_status');

        // 確認原本的物流資訊沒有被覆蓋
        $this->assertDatabaseHas('orders', [
            'id' => $order->id,
            'order_status' => 'shipped',
            'payment_status' => 'paid',
            'shipping_company' => '黑貓宅急便',
            'tracking_number' => 'ORIGINAL123',
        ]);

        // 確認原本出貨時間仍然存在
        $this->assertNotNull($order->fresh()->shipped_at);
    }

    // 測試沒有訂單 Session 的訪客無法查看成功頁面
    public function test_guest_cannot_view_order_without_session(): void
    {
        // 建立商家與測試訂單
        $seller = User::factory()->create(['role' => 'seller']);
        $order = $this->createTestOrder($seller);

        // 不提供訂單 Session，直接存取成功頁面
        $this->get(route('shop.checkout.success', [
            'site' => $order->site_id,
            'order' => $order->id,
        ]))->assertNotFound();
    }

    // 測試不能使用其他商店的網址查看訂單
    public function test_order_success_rejects_different_site(): void
    {
        // 建立兩個不同商家
        $sellerA = User::factory()->create(['role' => 'seller']);
        $sellerB = User::factory()->create(['role' => 'seller']);

        // 分別建立訂單
        $orderA = $this->createTestOrder($sellerA);
        $orderB = $this->createTestOrder($sellerB);

        // 模擬目前 Session 記錄商店 A 的訂單
        $this->withSession([
            'shop_last_order_' . $orderA->site_id => $orderA->id,
        ]);

        // 使用商店 A 的網址嘗試讀取商店 B 的訂單
        $this->get(route('shop.checkout.success', [
            'site' => $orderA->site_id,
            'order' => $orderB->id,
        ]))->assertNotFound();
    }

    // 測試只有目前 Session 記錄的訂單可以查看
    public function test_order_success_requires_matching_session(): void
    {
        // 建立商家與兩張訂單
        $seller = User::factory()->create(['role' => 'seller']);

        $orderA = $this->createTestOrder($seller);

        // 第二張訂單使用同一間商店
        $orderB = $orderA->replicate();
        $orderB->order_number = 'TEST-' . Str::upper(Str::random(16));
        $orderB->access_token = Str::random(64);
        $orderB->checkout_token = null;
        $orderB->save();

        // Session 只允許查看訂單 A
        $this->withSession([
            'shop_last_order_' . $orderA->site_id => $orderA->id,
        ]);

        // 訂單 A 可以正常查看
        $this->get(route('shop.checkout.success', [
            'site' => $orderA->site_id,
            'order' => $orderA->id,
        ]))->assertOk();

        // 訂單 B 即使屬於同一商店，也不能查看
        $this->get(route('shop.checkout.success', [
            'site' => $orderB->site_id,
            'order' => $orderB->id,
        ]))->assertNotFound();
    }


}
