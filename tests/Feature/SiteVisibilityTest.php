<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\Site;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SiteVisibilityTest extends TestCase
{
    use RefreshDatabase;

    // 建立指定商家擁有的網站
    private function createTestSite(User $seller, string $status): Site
    {
        return Site::create([
            'owner_user_id' => $seller->id,
            'name' => '測試商店',
            'slug' => 'visibility-' . uniqid(),
            'site_type' => 'ecommerce',
            'status' => $status,
            'settings' => [],
        ]);
    }

    // 建立網站的啟用商品
    private function createTestProduct(Site $site): Product
    {
        return Product::create([
            'site_id' => $site->id,
            'name' => '測試商品',
            'description' => '商店可見性測試',
            'price' => 100,
            'stock' => 10,
            'is_active' => true,
        ]);
    }

    // 未登入訪客不能瀏覽草稿商店
    public function test_guest_cannot_view_draft_site(): void
    {
        $seller = User::factory()->create(['role' => 'seller']);
        $site = $this->createTestSite($seller, 'draft');

        $this->get(route('shop.index', $site->id))
            ->assertNotFound();
    }

    // 商店擁有者可以預覽自己的草稿商店
    public function test_owner_can_preview_draft_site(): void
    {
        $seller = User::factory()->create(['role' => 'seller']);
        $site = $this->createTestSite($seller, 'draft');

        $this->actingAs($seller)
            ->get(route('shop.index', $site->id))
            ->assertOk();
    }

    // 其他商家不能預覽不屬於自己的草稿商店
    public function test_other_seller_cannot_preview_draft_site(): void
    {
        $owner = User::factory()->create(['role' => 'seller']);
        $otherSeller = User::factory()->create(['role' => 'seller']);

        $site = $this->createTestSite($owner, 'draft');

        $this->actingAs($otherSeller)
            ->get(route('shop.index', $site->id))
            ->assertNotFound();
    }

    // 已發布網站允許未登入訪客瀏覽
    public function test_guest_can_view_published_site(): void
    {
        $seller = User::factory()->create(['role' => 'seller']);
        $site = $this->createTestSite($seller, 'published');

        $this->get(route('shop.index', $site->id))
            ->assertOk();
    }

    // 未登入訪客不能查看草稿網站的商品詳細資料
    public function test_guest_cannot_view_draft_product(): void
    {
        $seller = User::factory()->create(['role' => 'seller']);
        $site = $this->createTestSite($seller, 'draft');
        $product = $this->createTestProduct($site);

        $this->get(route('shop.products.show', [
            'site' => $site->id,
            'product' => $product->id,
        ]))->assertNotFound();
    }

    // 已發布網站的商品詳細資料可以公開查看
    public function test_guest_can_view_published_product(): void
    {
        $seller = User::factory()->create(['role' => 'seller']);
        $site = $this->createTestSite($seller, 'published');
        $product = $this->createTestProduct($site);

        $this->get(route('shop.products.show', [
            'site' => $site->id,
            'product' => $product->id,
        ]))->assertOk();
    }
}
