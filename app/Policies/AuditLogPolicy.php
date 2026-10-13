<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\AuditLog;
use App\Models\User;

class AuditLogPolicy
{
    /**
     * Spec 10.10: audit events are readable only by authorized roles.
     */
    public function viewAny(User $user): bool
    {
        return $user->isActive() && in_array($user->role, [UserRole::SuperAdmin, UserRole::Auditor], true);
    }

    public function view(User $user, AuditLog $auditLog): bool
    {
        return $this->viewAny($user);
    }

    /** Append-only from the application's perspective. */
    public function update(User $user, AuditLog $auditLog): bool
    {
        return false;
    }

    public function delete(User $user, AuditLog $auditLog): bool
    {
        return false;
    }

    public function deleteAny(User $user): bool
    {
        return false;
    }
}
