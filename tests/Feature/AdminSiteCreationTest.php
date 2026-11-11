<?php

namespace Tests\Feature;

use App\Models\Site;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminSiteCreationTest extends TestCase
{
    use RefreshDatabase;

    private function createUserWithRole(string $role): User
    {
        $user = User::factory()->create();
        $user->role = $role;
        $user->save();

        return $user;
    }

    private function siteData(array $overrides = []): array
    {
        return array_merge([
            'name' => '測試商店',
            'slug' => 'admin-test-store',
            'site_type' => 'ecommerce',
        ], $overrides);
    }

    // Admin 可以替 Seller 建立商店
    public function test_admin_can_create_site_for_seller(): void
    {
        $admin = $this->createUserWithRole('admin');
        $seller = $this->createUserWithRole('seller');

        $response = $this->actingAs($admin)->post(
            '/sites',
            $this->siteData([
                'owner_user_id' => $seller->id,
            ])
        );

        $response->assertRedirect(route('sites.index'));

        $this->assertDatabaseHas('sites', [
            'slug' => 'admin-test-store',
            'owner_user_id' => $seller->id,
            'status' => 'draft',
        ]);
    }

    // Admin 不可以指定 Buyer
    public function test_admin_cannot_assign_buyer_as_owner(): void
    {
        $admin = $this->createUserWithRole('admin');
        $buyer = $this->createUserWithRole('buyer');

        $response = $this->actingAs($admin)->post(
            '/sites',
            $this->siteData([
                'owner_user_id' => $buyer->id,
            ])
        );

        $response->assertSessionHasErrors('owner_user_id');

        $this->assertDatabaseMissing('sites', [
            'slug' => 'admin-test-store',
        ]);
    }

    // Admin 不可以指定其他 Admin
    public function test_admin_cannot_assign_admin_as_owner(): void
    {
        $admin = $this->createUserWithRole('admin');
        $otherAdmin = $this->createUserWithRole('admin');

        $response = $this->actingAs($admin)->post(
            '/sites',
            $this->siteData([
                'owner_user_id' => $otherAdmin->id,
            ])
        );

        $response->assertSessionHasErrors('owner_user_id');

        $this->assertDatabaseMissing('sites', [
            'slug' => 'admin-test-store',
        ]);
    }

    // Admin 必須指定 Seller
    public function test_admin_must_select_seller(): void
    {
        $admin = $this->createUserWithRole('admin');

        $response = $this->actingAs($admin)->post(
            '/sites',
            $this->siteData()
        );

        $response->assertSessionHasErrors('owner_user_id');

        $this->assertDatabaseCount('sites', 0);
    }

    // Seller 建立商店時，自動成為擁有者
    public function test_seller_can_create_own_site(): void
    {
        $seller = $this->createUserWithRole('seller');

        $response = $this->actingAs($seller)->post(
            '/sites',
            $this->siteData()
        );

        $response->assertRedirect(route('sites.index'));

        $this->assertDatabaseHas('sites', [
            'slug' => 'admin-test-store',
            'owner_user_id' => $seller->id,
        ]);
    }

    // Seller 無法偽造其他 Seller 的 owner_user_id
    public function test_seller_cannot_assign_another_seller(): void
    {
        $seller = $this->createUserWithRole('seller');
        $otherSeller = $this->createUserWithRole('seller');

        $this->actingAs($seller)->post(
            '/sites',
            $this->siteData([
                'owner_user_id' => $otherSeller->id,
            ])
        );

        $this->assertDatabaseHas('sites', [
            'slug' => 'admin-test-store',
            'owner_user_id' => $seller->id,
        ]);

        $this->assertDatabaseMissing('sites', [
            'slug' => 'admin-test-store',
            'owner_user_id' => $otherSeller->id,
        ]);
    }

    // Buyer 無法建立商店
    public function test_buyer_cannot_create_site(): void
    {
        $buyer = $this->createUserWithRole('buyer');

        $response = $this->actingAs($buyer)->post(
            '/sites',
            $this->siteData()
        );

        $response->assertForbidden();

        $this->assertDatabaseCount('sites', 0);
    }

    // Admin 建立網站頁面應提供 Seller 清單
    public function test_admin_create_page_contains_seller_list(): void
    {
        $admin = $this->createUserWithRole('admin');
        $seller = $this->createUserWithRole('seller');
        $buyer = $this->createUserWithRole('buyer');

        $response = $this->actingAs($admin)->get('/sites/create');

        $response->assertOk();

        $response->assertInertia(fn ($page) => $page
            ->component('Sites/Create')
            ->where('isAdmin', true)
            ->has('sellers', 1)
            ->where('sellers.0.id', $seller->id)
            ->missing('sellers.1')
        );
    }

    // Seller 建立網站頁面不應取得其他 Seller 清單
    public function test_seller_create_page_does_not_receive_seller_list(): void
    {
        $seller = $this->createUserWithRole('seller');
        $this->createUserWithRole('seller');

        $response = $this->actingAs($seller)->get('/sites/create');

        $response->assertOk();

        $response->assertInertia(fn ($page) => $page
            ->component('Sites/Create')
            ->where('isAdmin', false)
            ->has('sellers', 0)
        );
    }

    // Admin 建立商店後，指定的 Seller 可以發布
    public function test_seller_can_publish_site_created_by_admin(): void
    {
        $admin = $this->createUserWithRole('admin');
        $seller = $this->createUserWithRole('seller');

        // Admin 替 Seller 建立商店
        $this->actingAs($admin)->post('/sites', $this->siteData([
            'owner_user_id' => $seller->id,
        ]))->assertRedirect(route('sites.index'));

        $site = Site::where('slug', 'admin-test-store')->firstOrFail();

        // Seller 發布自己的商店
        $this->actingAs($seller)
            ->patch(route('sites.publish', $site))
            ->assertRedirect(route('sites.index'));

        $this->assertDatabaseHas('sites', [
            'id' => $site->id,
            'owner_user_id' => $seller->id,
            'status' => 'published',
        ]);
    }

    // Admin 不可以代替 Seller 發布商店
    public function test_admin_cannot_publish_seller_site(): void
    {
        $admin = $this->createUserWithRole('admin');
        $seller = $this->createUserWithRole('seller');

        $this->actingAs($admin)->post('/sites', $this->siteData([
            'owner_user_id' => $seller->id,
        ]));

        $site = Site::where('slug', 'admin-test-store')->firstOrFail();

        $this->actingAs($admin)
            ->patch(route('sites.publish', $site))
            ->assertForbidden();

        $this->assertDatabaseHas('sites', [
            'id' => $site->id,
            'status' => 'draft',
        ]);
    }


}
