<?php

namespace Tests\Feature;

use App\Models\Site;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminDashboardTest extends TestCase
{
    use RefreshDatabase;

    private function createUserWithRole(string $role): User
    {
        $user = User::factory()->create();
        $user->role = $role;
        $user->save();

        return $user;
    }

    // Admin 可以進入管理後台
    public function test_admin_can_view_dashboard(): void
    {
        $admin = $this->createUserWithRole('admin');

        $this->actingAs($admin)
            ->get(route('admin.dashboard'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Admin/Dashboard')
                ->has('stats')
            );
    }

    // Seller 不可以進入管理後台
    public function test_seller_cannot_view_dashboard(): void
    {
        $seller = $this->createUserWithRole('seller');

        $this->actingAs($seller)
            ->get(route('admin.dashboard'))
            ->assertForbidden();
    }

    // Buyer 不可以進入管理後台
    public function test_buyer_cannot_view_dashboard(): void
    {
        $buyer = $this->createUserWithRole('buyer');

        $this->actingAs($buyer)
            ->get(route('admin.dashboard'))
            ->assertForbidden();
    }

    // 未登入使用者應導向登入頁面
    public function test_guest_cannot_view_dashboard(): void
    {
        $this->get(route('admin.dashboard'))
            ->assertRedirect(route('login'));
    }

    // Admin 可以查看正確的平台統計資料
    public function test_admin_dashboard_statistics_are_correct(): void
    {
        $admin = $this->createUserWithRole('admin');
        $seller = $this->createUserWithRole('seller');

        Site::create([
            'owner_user_id' => $seller->id,
            'name' => '草稿商店',
            'slug' => 'draft-store',
            'site_type' => 'ecommerce',
            'status' => 'draft',
            'settings' => [],
        ]);

        Site::create([
            'owner_user_id' => $seller->id,
            'name' => '正式商店',
            'slug' => 'published-store',
            'site_type' => 'ecommerce',
            'status' => 'published',
            'settings' => [],
        ]);

        $this->actingAs($admin)
            ->get(route('admin.dashboard'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Admin/Dashboard')
                ->where('stats.sellers', 1)
                ->where('stats.sites', 2)
                ->where('stats.published', 1)
                ->where('stats.draft', 1)
            );
    }
}
