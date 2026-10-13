<?php

namespace App\Services\Surveys;

use App\Enums\AssignmentPolicy;
use App\Enums\SurveyStatus;
use App\Models\Site;
use App\Models\Survey;
use App\Models\SurveyAssignment;

/**
 * Decides which survey version a site is currently serving.
 *
 * FR-RESP-002: only an active survey with a published version, assigned inside
 * its schedule, at an active site, may accept or display responses.
 */
class ResolveSurvey
{
    public function forSite(Site $site): ?ResolvedSurvey
    {
        if (! $site->acceptsResponses()) {
            return null;
        }

        $assignment = SurveyAssignment::query()
            ->where('status', 'active')
            // whereIn, not where: a `where` with a query builder compiles to
            // `= (subquery)`, which silently picks the wrong survey.
            ->whereIn('survey_id', Survey::query()->select('id')->where('status', SurveyStatus::Active))
            ->where(fn ($query) => $query->where('site_id', $site->id)->orWhereNull('site_id'))
            ->where(fn ($query) => $query->whereNull('starts_at')->orWhere('starts_at', '<=', now()))
            ->where(fn ($query) => $query->whereNull('ends_at')->orWhere('ends_at', '>=', now()))
            // A site-specific assignment wins over a global one.
            ->orderByRaw('case when site_id is null then 1 else 0 end')
            ->latest('id')
            ->with('survey.publishedVersion')
            ->first();

        if ($assignment === null) {
            return null;
        }

        $version = $assignment->survey->publishedVersion;

        // An empty survey would accept submissions and store nothing, which is
        // worse than telling the public that feedback is unavailable.
        if ($version === null || $version->questions() === []) {
            return null;
        }

        // Fail safe: never block a public user's Wi-Fi access on the strength of
        // a configuration that has not been proven against a real gateway
        // (Plan section 9.3, FR-CAP-007).
        $enforced = $assignment->policy;

        if ($enforced->blocksAccess() && ! $site->integration_mode->isVerifiedCapable()) {
            report(new \RuntimeException(sprintf(
                'Assignment #%d requests mandatory submission at site %s, but the site has no verified portal integration. Downgraded to optional.',
                $assignment->id,
                $site->site_code,
            )));

            return new ResolvedSurvey($assignment->survey, $version, AssignmentPolicy::Optional, policyEnforced: false);
        }

        return new ResolvedSurvey($assignment->survey, $version, $assignment->policy);
    }
}
