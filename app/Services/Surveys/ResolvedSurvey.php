<?php

namespace App\Services\Surveys;

use App\Enums\AssignmentPolicy;
use App\Models\Site;
use App\Models\Survey;
use App\Models\SurveyVersion;

/**
 * The outcome of resolving which survey a site is currently serving, and the
 * policy that is actually safe to enforce for it.
 */
final readonly class ResolvedSurvey
{
    public function __construct(
        public Survey $survey,
        public SurveyVersion $version,
        public AssignmentPolicy $policy,
        /** False when a mandatory policy was downgraded because the site's gateway integration is not verified. */
        public bool $policyEnforced = true,
    ) {}

    public function requiresSubmissionBeforeAccess(): bool
    {
        return $this->policy->blocksAccess() && $this->policyEnforced;
    }
}
