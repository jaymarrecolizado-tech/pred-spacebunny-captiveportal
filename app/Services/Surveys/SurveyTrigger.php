<?php

namespace App\Services\Surveys;

use App\Enums\SurveyTriggerMode;
use App\Models\Site;
use App\Models\SurveyResponse;
use Carbon\Carbon;
use Carbon\CarbonInterface;

/**
 * Decides whether a connecting device must complete the survey before it gets
 * internet access (FR-RESP-002, FR-CAP-007).
 *
 * Two rules, in this order, and they matter more than the setting:
 *
 *  1. A device that has NEVER answered is always asked, whatever the setting.
 *     Otherwise "once every 7 days" would silently exclude anyone who only ever
 *     connects once a month, and they would never be surveyed at all.
 *  2. `Never` is honoured for everyone, including first-timers. That is how a
 *     location is switched off, and an operator who turned it off meant it.
 *
 * Devices with no identifiable reference (a QR code or site link) cannot be
 * tracked, so they are asked every time. That is the pre-existing behaviour and
 * the only fail-safe choice: asking twice is a minor annoyance, skipping a
 * member of the public forever is a coverage failure we cannot detect.
 */
class SurveyTrigger
{
    public function shouldAsk(Site $site, ?string $deviceReferenceHash): bool
    {
        $mode = $site->survey_trigger_mode ?? SurveyTriggerMode::Always;

        if ($mode === SurveyTriggerMode::Never) {
            return false;
        }

        if ($deviceReferenceHash === null || $deviceReferenceHash === '') {
            return true;
        }

        $lastAnsweredAt = $this->lastAnsweredAt($site, $deviceReferenceHash);

        // Rule 1: never answered before.
        if ($lastAnsweredAt === null) {
            return true;
        }

        if ($mode === SurveyTriggerMode::Always) {
            return true;
        }

        // Rule: Interval. Ask again once the window has passed.
        $days = max(1, (int) $site->survey_trigger_days);

        return $lastAnsweredAt->addDays($days)->isPast();
    }

    /**
     * When this device last answered at this site, if ever.
     *
     * Scoped to the site on purpose: someone who answered at one location is a
     * first-timer at the next one, so rolling this up across sites would let a
     * frequent traveller never be asked anywhere.
     */
    private function lastAnsweredAt(Site $site, string $deviceReferenceHash): ?CarbonInterface
    {
        // max() on an aggregate returns a raw string, so it is parsed rather than
        // assumed to already be a Carbon instance.
        $value = SurveyResponse::query()
            ->where('site_id', $site->id)
            ->where('session_reference_hash', $deviceReferenceHash)
            ->max('submitted_at');

        return $value === null ? null : Carbon::parse($value);
    }
}
