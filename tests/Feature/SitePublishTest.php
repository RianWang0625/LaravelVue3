<?php

namespace Tests\Feature;

use App\Models\Site;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SitePublishTest extends TestCase
{
    use RefreshDatabase;

    // 建立指定商家擁有的草稿網站
    private function createDraftSite(User $seller): Site
    {
        return Site::create([
            'owner_user_id' => $seller->id,
            'name' => '測試網站',
            'slug' => 'publish-test-' . uniqid(),
            'site_type' => 'ecommerce',
            'status' => 'draft',
            'settings' => [],
        ]);
    }

    // 網站擁有者可以發布自己的網站
    public function test_owner_can_publish_draft_site(): void
    {
        $seller = User::factory()->create(['role' => 'seller']);
        $site = $this->createDraftSite($seller);

        $this->actingAs($seller)
            ->patch(route('sites.publish', $site->id))
            ->assertRedirect(route('sites.index'));

        $this->assertDatabaseHas('sites', [
            'id' => $site->id,
            'status' => 'published',
        ]);
    }

    // 其他商家不能發布不屬於自己的網站
    public function test_other_seller_cannot_publish_site(): void
    {
        $owner = User::factory()->create(['role' => 'seller']);
        $otherSeller = User::factory()->create(['role' => 'seller']);

        $site = $this->createDraftSite($owner);

        $this->actingAs($otherSeller)
            ->patch(route('sites.publish', $site->id))
            ->assertForbidden();

        $this->assertDatabaseHas('sites', [
            'id' => $site->id,
            'status' => 'draft',
        ]);
    }

    // 一般買家不能發布網站
    public function test_buyer_cannot_publish_site(): void
    {
        $seller = User::factory()->create(['role' => 'seller']);
        $buyer = User::factory()->create(['role' => 'buyer']);

        $site = $this->createDraftSite($seller);

        $this->actingAs($buyer)
            ->patch(route('sites.publish', $site->id))
            ->assertForbidden();

        $this->assertDatabaseHas('sites', [
            'id' => $site->id,
            'status' => 'draft',
        ]);
    }

    // 未登入訪客不能發布網站
    public function test_guest_cannot_publish_site(): void
    {
        $seller = User::factory()->create(['role' => 'seller']);
        $site = $this->createDraftSite($seller);

        $this->patch(route('sites.publish', $site->id))
            ->assertRedirect(route('login'));

        $this->assertDatabaseHas('sites', [
            'id' => $site->id,
            'status' => 'draft',
        ]);
    }

    // 已發布網站不能重複發布
    public function test_published_site_cannot_be_published_again(): void
    {
        $seller = User::factory()->create(['role' => 'seller']);
        $site = $this->createDraftSite($seller);

        // 第一次發布
        $this->actingAs($seller)
            ->patch(route('sites.publish', $site->id))
            ->assertRedirect();

        // 第二次發布應回傳驗證錯誤
        $this->patch(route('sites.publish', $site->id))
            ->assertSessionHasErrors('status');

        $this->assertDatabaseHas('sites', [
            'id' => $site->id,
            'status' => 'published',
        ]);
    }
}
