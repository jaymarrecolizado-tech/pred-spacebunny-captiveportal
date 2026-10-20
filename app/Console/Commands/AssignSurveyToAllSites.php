<?php

namespace App\Console\Commands;

use App\Enums\AssignmentPolicy;
use App\Models\Survey;
use App\Models\SurveyAssignment;
use Illuminate\Console\Command;

/**
 * Gives a survey to every location at once.
 *
 * A survey only reaches a location through a survey assignment. Without one,
 * the location shows the public "feedback is not available" page - which is
 * exactly what a captive portal pointing at us would show the person trying
 * to get online.
 *
 * A global assignment (one row, no site) covers every location that is
 * switched on, so the pilot does not need 193 separate rows.
 *
 * Run it once on the server:
 *
 *   php artisan survey:assign-all
 */
class AssignSurveyToAllSites extends Command
{
    protected $signature = 'survey:assign-all
                            {--survey= : Name or id of the survey. Defaults to the only active one.}
                            {--policy=optional : optional or mandatory}
                            {--dry-run : Show what would change without writing}';

    protected $description = 'Give the live survey to every location, through one global assignment';

    public function handle(): int
    {
        $survey = $this->findSurvey();

        if ($survey === null) {
            return self::FAILURE;
        }

        $policy = $this->option('policy');
        $allowed = array_column(AssignmentPolicy::cases(), 'value');

        if (! in_array($policy, $allowed, true)) {
            $this->error('Policy must be one of: '.implode(', ', $allowed));

            return self::FAILURE;
        }

        // Already covered: an active, in-schedule global assignment means every
        // location that is switched on already gets this survey.
        $existing = SurveyAssignment::query()
            ->where('survey_id', $survey->id)
            ->whereNull('site_id')
            ->where('status', 'active')
            ->get()
            ->filter(fn (SurveyAssignment $assignment) => $assignment->isWithinSchedule())
            ->first();

        if ($existing !== null) {
            $this->info(sprintf(
                '"%s" is already assigned to all locations (assignment #%d, policy %s). Nothing to do.',
                $survey->name,
                $existing->id,
                $existing->policy->value,
            ));

            return self::SUCCESS;
        }

        $this->info(sprintf('Survey: %s', $survey->name));
        $this->line('  policy: '.$policy);

        if ($this->option('dry-run')) {
            $this->comment('Dry run: nothing was written.');

            return self::SUCCESS;
        }

        $assignment = SurveyAssignment::create([
            'survey_id' => $survey->id,
            // No site: this is the "everywhere" assignment.
            'site_id' => null,
            'policy' => $policy,
            'status' => 'active',
        ]);

        $this->info(sprintf(
            'Assignment #%d created. Every location that is switched on will now serve this survey.',
            $assignment->id,
        ));

        return self::SUCCESS;
    }

    private function findSurvey(): ?Survey
    {
        $name = $this->option('survey');

        $surveys = Survey::query()
            ->where('status', 'active')
            ->whereNotNull('published_version_id')
            ->when($name !== null, fn ($query) => $query->where('name', $name)->orWhere('id', $name))
            ->get()
            ->filter(fn (Survey $survey) => $survey->publishedVersion !== null
                && $survey->publishedVersion->questions() !== [])
            ->values();

        if ($surveys->isEmpty()) {
            $this->error($name === null
                ? 'No active survey with a published version. Publish one in the admin panel first.'
                : "No active survey matches \"{$name}\".");

            return null;
        }

        if ($surveys->count() > 1) {
            $this->error('More than one survey is live. Pick one with --survey=...:');

            foreach ($surveys as $survey) {
                $this->line(sprintf('  %d  %s', $survey->id, $survey->name));
            }

            return null;
        }

        return $surveys->first();
    }
}
