<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class ProductOwnershipTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;
    private User $otherUser;
    private Product $product;
    private ProductVariant $variant;

    protected function setUp(): void
    {
        parent::setUp();

        // 建立兩個不同的 Seller 帳號
        $this->owner = User::factory()->create([
            'role' => 'seller',
        ]);

        $this->otherUser = User::factory()->create([
            'role' => 'seller',
        ]);

        // 建立商家 A 的網站
        $siteData = [
            'owner_user_id' => $this->owner->id,
            'name' => '商家 A 測試網站',
            'slug' => 'owner-test-shop',
            'created_at' => now(),
            'updated_at' => now(),
        ];

        if (Schema::hasColumn('sites', 'type')) {
            $siteData['type'] = 'ecommerce';
        }

        if (Schema::hasColumn('sites', 'site_type')) {
            $siteData['site_type'] = 'ecommerce';
        }

        if (Schema::hasColumn('sites', 'status')) {
            $siteData['status'] = 'draft';
        }

        $siteId = DB::table('sites')->insertGetId($siteData);

        // 建立商家 A 的商品
        $this->product = Product::create([
            'site_id' => $siteId,
            'name' => '測試商品 A',
            'description' => '權限測試商品',
            'price' => 1290,
            'stock' => 20,
            'is_active' => true,
        ]);

        // 建立商家 A 的商品規格
        $this->variant = $this->product->variants()->create([
            'sku' => 'TEST-BLACK',
            'options' => [
                '顏色' => '黑色',
            ],
            'price' => 1290,
            'stock' => 10,
            'is_active' => true,
        ]);
    }

    // 測試其他商家不能修改商品
    public function test_other_user_cannot_update_product(): void
    {
        $this->actingAs($this->otherUser)
            ->put("/products/{$this->product->id}", [
                'name' => '非法修改商品',
                'description' => '測試',
                'price' => 1,
                'stock' => 999,
                'is_active' => true,
            ])
            ->assertNotFound();

        $this->assertDatabaseHas('products', [
            'id' => $this->product->id,
            'name' => '測試商品 A',
            'stock' => 20,
        ]);
    }

    // 測試其他商家不能刪除商品
    public function test_other_user_cannot_delete_product(): void
    {
        $this->actingAs($this->otherUser)
            ->delete("/products/{$this->product->id}")
            ->assertNotFound();

        $this->assertDatabaseHas('products', [
            'id' => $this->product->id,
        ]);
    }

    // 測試其他商家不能修改商品規格
    public function test_other_user_cannot_update_variant(): void
    {
        $this->actingAs($this->otherUser)
            ->put(
                "/products/{$this->product->id}/variants/{$this->variant->id}",
                [
                    'sku' => 'HACKED-SKU',
                    'options' => ['顏色' => '白色'],
                    'price' => 1,
                    'stock' => 999,
                    'is_active' => true,
                ]
            )
            ->assertNotFound();

        $this->assertDatabaseHas('product_variants', [
            'id' => $this->variant->id,
            'sku' => 'TEST-BLACK',
            'stock' => 10,
        ]);
    }

    // 測試其他商家不能刪除商品規格
    public function test_other_user_cannot_delete_variant(): void
    {
        $this->actingAs($this->otherUser)
            ->delete(
                "/products/{$this->product->id}/variants/{$this->variant->id}"
            )
            ->assertNotFound();

        $this->assertDatabaseHas('product_variants', [
            'id' => $this->variant->id,
        ]);
    }

    // Buyer cannot manage products even if they own a site
    public function test_buyer_cannot_manage_products(): void
    {
        // Simulate an existing seller downgraded to buyer
        $this->owner->role = 'buyer';
        $this->owner->save();

        $this->actingAs($this->owner)
            ->get(route('products.index'))
            ->assertForbidden();

        $this->actingAs($this->owner)
            ->post(route('products.store'), [
                'site_id' => $this->product->site_id,
                'name' => 'Unauthorized Product',
                'price' => 100,
                'stock' => 10,
            ])
            ->assertForbidden();

        $this->actingAs($this->owner)
            ->put("/products/{$this->product->id}", [
                'name' => 'Unauthorized Update',
                'price' => 1,
                'stock' => 999,
                'is_active' => true,
            ])
            ->assertForbidden();

        $this->actingAs($this->owner)
            ->delete("/products/{$this->product->id}")
            ->assertForbidden();

        $this->assertDatabaseHas('products', [
            'id' => $this->product->id,
            'name' => '測試商品 A',
            'stock' => 20,
        ]);

        $this->assertDatabaseMissing('products', [
            'name' => 'Unauthorized Product',
        ]);
    }

    // 測試 Buyer 無法管理商品規格
    public function test_buyer_cannot_manage_variants(): void
    {
        // 模擬原本的 Seller 被改成 Buyer
        $this->owner->role = 'buyer';
        $this->owner->save();

        // 驗證 Buyer 無法新增商品規格
        $this->actingAs($this->owner)
            ->post("/products/{$this->product->id}/variants", [
                'sku' => 'UNAUTHORIZED-SKU',
                'options' => ['顏色' => '紅色'],
                'price' => 100,
                'stock' => 50,
                'is_active' => true,
            ])
            ->assertForbidden();

        // 驗證 Buyer 無法修改商品規格
        $this->actingAs($this->owner)
            ->put(
                "/products/{$this->product->id}/variants/{$this->variant->id}",
                [
                    'sku' => 'HACKED-SKU',
                    'options' => ['顏色' => '白色'],
                    'price' => 1,
                    'stock' => 999,
                    'is_active' => true,
                ]
            )
            ->assertForbidden();

        // 驗證 Buyer 無法刪除商品規格
        $this->actingAs($this->owner)
            ->delete(
                "/products/{$this->product->id}/variants/{$this->variant->id}"
            )
            ->assertForbidden();

        // 確認原本的商品規格沒有被修改或刪除
        $this->assertDatabaseHas('product_variants', [
            'id' => $this->variant->id,
            'sku' => 'TEST-BLACK',
            'stock' => 10,
        ]);

        // 確認沒有新增未授權的商品規格
        $this->assertDatabaseMissing('product_variants', [
            'sku' => 'UNAUTHORIZED-SKU',
        ]);
    }


}
