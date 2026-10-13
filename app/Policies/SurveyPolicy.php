<?php

namespace App\Policies;

use App\Models\Survey;
use App\Models\User;

class SurveyPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isActive();
    }

    public function view(User $user, Survey $survey): bool
    {
        return $user->isActive();
    }

    public function create(User $user): bool
    {
        return $user->isActive() && $user->role->managesSurveys();
    }

    public function update(User $user, Survey $survey): bool
    {
        return $user->isActive() && $user->role->managesSurveys();
    }

    public function delete(User $user, Survey $survey): bool
    {
        return $user->isActive() && $user->role->managesSurveys() && $survey->status->value === 'draft';
    }

    public function deleteAny(User $user): bool
    {
        return false;
    }
}
