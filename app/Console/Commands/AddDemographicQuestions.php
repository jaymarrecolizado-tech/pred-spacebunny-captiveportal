<?php

namespace App\Console\Commands;

use App\Enums\SurveyVersionStatus;
use App\Models\Survey;
use App\Services\Surveys\DemographicQuestions;
use Illuminate\Console\Command;

/**
 * Adds the gender and sector questions to the survey that is live.
 *
 * A published version cannot be edited, so this copies the live version into
 * the next version with the extra questions, publishes it and points the
 * survey at it. Answers already collected keep pointing at the version they
 * were answered on, so history is untouched.
 *
 * Run it once on the server:
 *
 *   php artisan survey:add-demographics
 */
class AddDemographicQuestions extends Command
{
    protected $signature = 'survey:add-demographics
                            {--survey= : Name or id of the survey. Only needed when more than one is live}
                            {--dry-run : Show what would change without writing}';

    protected $description = 'Add the gender and sector questions to the live survey as a new published version';

    public function handle(): int
    {
        $survey = $this->findSurvey();

        if ($survey === null) {
            return self::FAILURE;
        }

        $live = $survey->publishedVersion;
        $questions = $live->questions();
        $existing = array_column($questions, 'key');

        $toAdd = array_values(array_filter(
            DemographicQuestions::questions(),
            fn (array $question): bool => ! in_array($question['key'], $existing, true),
        ));

        if ($toAdd === []) {
            $this->info('The live version already has these questions. Nothing to do.');

            return self::SUCCESS;
        }

        $this->info(sprintf('Survey: %s (live version %d)', $survey->name, $live->version_number));

        foreach ($toAdd as $question) {
            $this->line('  + '.$question['label']);
        }

        if ($this->option('dry-run')) {
            $this->comment('Dry run: nothing was written.');

            return self::SUCCESS;
        }

        $version = $survey->versions()->create([
            'version_number' => ((int) $survey->versions()->max('version_number')) + 1,
            'schema_json' => ['questions' => array_merge($questions, $toAdd)],
            'privacy_notice_version' => $live->privacy_notice_version,
            'status' => SurveyVersionStatus::Published,
            'published_at' => now(),
            'published_by' => null,
        ]);

        $survey->update(['published_version_id' => $version->id]);

        $this->info(sprintf('Version %d published and is now live. People answering the survey will see the new questions.',
            $version->version_number,
        ));

        return self::SUCCESS;
    }

    /**
     * The survey to add the questions to.
     *
     * Guessing would be dangerous: adding the questions to the wrong survey
     * would change what the public is asked somewhere they never expected it.
     */
    private function findSurvey(): ?Survey
    {
        $name = $this->option('survey');

        $surveys = Survey::query()
            ->whereNotNull('published_version_id')
            ->when($name !== null, fn ($query) => $query->where('name', $name)->orWhere('id', $name))
            ->get();

        if ($surveys->isEmpty()) {
            $this->error($name === null
                ? 'No survey has a live version yet. Publish one in the admin panel first.'
                : "No live survey matches \"{$name}\".");

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
