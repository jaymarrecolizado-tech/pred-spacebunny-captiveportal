<?php

namespace App\Policies;

use App\Models\SurveyResponse;
use App\Models\User;

class SurveyResponsePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isActive();
    }

    public function view(User $user, SurveyResponse $response): bool
    {
        return $user->isActive() && $response->isVisibleTo($user);
    }

    /**
     * Responses are created by the public endpoints, never by the admin UI.
     */
    public function create(User $user): bool
    {
        return false;
    }

    /**
     * FR-RESP-012 (correction/quarantine/deletion) is a controlled process that
     * DICT has not approved yet. Until it exists, accepted answers are not
     * editable and nothing may delete them.
     */
    public function update(User $user, SurveyResponse $response): bool
    {
        return false;
    }

    public function delete(User $user, SurveyResponse $response): bool
    {
        return false;
    }

    public function deleteAny(User $user): bool
    {
        return false;
    }
}
