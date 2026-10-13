<?php

namespace App\Policies;

use App\Models\Site;
use App\Models\User;

class SitePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isActive();
    }

    public function view(User $user, Site $site): bool
    {
        return $user->isActive() && $site->isVisibleTo($user);
    }

    public function create(User $user): bool
    {
        return $user->isActive() && $user->role->managesRegistry();
    }

    public function update(User $user, Site $site): bool
    {
        return $user->isActive() && $user->role->managesRegistry() && $site->isVisibleTo($user);
    }

    /** Sites are deactivated, never erased (FR-SITE-007). */
    public function delete(User $user, Site $site): bool
    {
        return $this->update($user, $site);
    }

    public function restore(User $user, Site $site): bool
    {
        return $user->isActive() && $user->role->managesRegistry() && $site->isVisibleTo($user);
    }

    public function forceDelete(User $user, Site $site): bool
    {
        return false;
    }

    public function deleteAny(User $user): bool
    {
        return false;
    }

    public function restoreAny(User $user): bool
    {
        return false;
    }

    public function forceDeleteAny(User $user): bool
    {
        return false;
    }
}
