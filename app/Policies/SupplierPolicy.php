<?php

namespace App\Policies;

use App\Models\Supplier;
use App\Models\User;

class SupplierPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isActive();
    }

    /**
     * A supplier administrator may only see its own supplier record.
     */
    public function view(User $user, Supplier $supplier): bool
    {
        if (! $user->isActive()) {
            return false;
        }

        if ($user->role->managesRegistry()) {
            return true;
        }

        return (int) $supplier->id === (int) $user->scope_supplier_id;
    }

    public function create(User $user): bool
    {
        return $user->isActive() && $user->role->managesRegistry();
    }

    public function update(User $user, Supplier $supplier): bool
    {
        return $user->isActive() && $user->role->managesRegistry() && $this->view($user, $supplier);
    }

    public function delete(User $user, Supplier $supplier): bool
    {
        return $this->update($user, $supplier);
    }

    public function deleteAny(User $user): bool
    {
        return false;
    }
}
