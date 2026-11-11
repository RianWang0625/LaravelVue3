<?php

namespace Tests\Feature;

use App\Models\Site;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SitePolicyTest extends TestCase
{
    use RefreshDatabase;

    // Seller can view store management
    public function test_seller_can_view_store_list(): void
    {
        $seller = User::factory()->create(['role' => 'seller']);

        $this->actingAs($seller)
            ->get(route('sites.index'))
            ->assertOk();
    }

    // Seller can open store creation page
    public function test_seller_can_open_create_page(): void
    {
        $seller = User::factory()->create(['role' => 'seller']);

        $this->actingAs($seller)
            ->get(route('sites.create'))
            ->assertOk();
    }

    // Buyer cannot view store management
    public function test_buyer_cannot_view_store_list(): void
    {
        $buyer = User::factory()->create(['role' => 'buyer']);

        $this->actingAs($buyer)
            ->get(route('sites.index'))
            ->assertForbidden();
    }

    // Buyer cannot create stores
    public function test_buyer_cannot_create_store(): void
    {
        $buyer = User::factory()->create(['role' => 'buyer']);

        $this->actingAs($buyer)
            ->post(route('sites.store'), [
                'name' => 'Unauthorized Store',
                'slug' => 'unauthorized-store',
                'site_type' => 'ecommerce',
            ])
            ->assertForbidden();

        $this->assertDatabaseMissing('sites', [
            'slug' => 'unauthorized-store',
        ]);
    }

    // Seller can only see stores they own
    public function test_seller_only_sees_own_stores(): void
    {
        $sellerA = User::factory()->create(['role' => 'seller']);
        $sellerB = User::factory()->create(['role' => 'seller']);

        $ownSite = Site::create([
            'owner_user_id' => $sellerA->id,
            'name' => 'Seller A Store',
            'slug' => 'seller-a-store',
            'site_type' => 'ecommerce',
            'status' => 'draft',
            'settings' => [],
        ]);

        $otherSite = Site::create([
            'owner_user_id' => $sellerB->id,
            'name' => 'Seller B Store',
            'slug' => 'seller-b-store',
            'site_type' => 'ecommerce',
            'status' => 'draft',
            'settings' => [],
        ]);

        $this->actingAs($sellerA)
            ->get(route('sites.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Sites/Index')
                ->has('sites', 1)
                ->where('sites.0.id', $ownSite->id)
            );
    }

    // New stores must belong to the logged-in seller
    public function test_new_store_belongs_to_logged_in_seller(): void
    {
        $seller = User::factory()->create(['role' => 'seller']);

        $this->actingAs($seller)
            ->post(route('sites.store'), [
                'name' => 'My New Store',
                'slug' => 'my-new-store',
                'site_type' => 'ecommerce',
                'owner_user_id' => 9999,
            ])
            ->assertRedirect(route('sites.index'));

        $this->assertDatabaseHas('sites', [
            'slug' => 'my-new-store',
            'owner_user_id' => $seller->id,
        ]);
    }

}
