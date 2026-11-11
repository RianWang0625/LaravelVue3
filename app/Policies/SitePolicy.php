<?php

namespace App\Policies;

use App\Models\Site;
use App\Models\User;

class SitePolicy
{
    // View seller's store list
    public function viewAny(User $user): bool
    {
        return $user->isSeller() || $user->isAdmin();
    }

    // View a specific store
    public function view(User $user, Site $site): bool
    {
        return $user->isSeller()
            && $site->owner_user_id === $user->id;
    }

    // Create a store
    public function create(User $user): bool
    {
        return $user->isSeller() || $user->isAdmin();
    }

    // Update a store
    public function update(User $user, Site $site): bool
    {
        return $user->isSeller()
            && $site->owner_user_id === $user->id;
    }

    // Delete a store
    public function delete(User $user, Site $site): bool
    {
        return $user->isSeller()
            && $site->owner_user_id === $user->id;
    }
}
