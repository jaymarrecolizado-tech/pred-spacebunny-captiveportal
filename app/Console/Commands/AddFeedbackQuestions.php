<?php

namespace App\Console\Commands;

use App\Enums\SurveyVersionStatus;
use App\Models\Survey;
use App\Services\Surveys\FeedbackQuestions;
use Illuminate\Console\Command;

/**
 * Replaces the open "anything else" text box with a dropdown of generic
 * answers plus a short free-text box for people who choose "Something else".
 *
 * A published version cannot be edited, so this copies the live version into
 * the next version, swaps the question, publishes it and points the survey at
 * it. Answers already collected keep pointing at the version they were answered
 * on, so history is untouched.
 *
 * Run it once on the server:
 *
 *   php artisan survey:add-feedback
 */
class AddFeedbackQuestions extends Command
{
    protected $signature = 'survey:add-feedback
                            {--survey= : Name or id of the survey. Only needed when more than one is live}
                            {--dry-run : Show what would change without writing}';

    protected $description = 'Replace the open comment box with an experience dropdown and a short optional follow-up';

    public function handle(): int
    {
        $survey = $this->findSurvey();

        if ($survey === null) {
            return self::FAILURE;
        }

        $live = $survey->publishedVersion;
        $questions = $live->questions();
        $existingKeys = array_column($questions, 'key');

        // Already live: nothing to change, and no new version to publish.
        if (in_array('feedback', $existingKeys, true) && ! in_array('comment', $existingKeys, true)) {
            $this->info('The live version already has the experience dropdown. Nothing to do.');

            return self::SUCCESS;
        }

        // The old open text box goes away; the dropdown and its short
        // follow-up take its place.
        $questions = array_values(array_filter(
            $questions,
            fn (array $question): bool => ($question['key'] ?? null) !== 'comment',
        ));

        $toAdd = FeedbackQuestions::questions();

        foreach ($toAdd as $question) {
            $questions[] = $question;
        }

        $this->info(sprintf('Survey: %s (live version %d)', $survey->name, $live->version_number));

        foreach ($toAdd as $question) {
            $this->line('  + '.$question['label']);
        }

        $this->line('  - Anything else you want to tell us? (open text box removed)');

        if ($this->option('dry-run')) {
            $this->comment('Dry run: nothing was written.');

            return self::SUCCESS;
        }

        $version = $survey->versions()->create([
            'version_number' => ((int) $survey->versions()->max('version_number')) + 1,
            'schema_json' => ['questions' => $questions],
            'privacy_notice_version' => $live->privacy_notice_version,
            'status' => SurveyVersionStatus::Published,
            'published_at' => now(),
            'published_by' => null,
        ]);

        $survey->update(['published_version_id' => $version->id]);

        $this->info(sprintf('Version %d published and is now live. People answering the survey now pick from a list, and can add up to %d characters if they choose "Something else".',
            $version->version_number,
            FeedbackQuestions::MAX_COMMENT_LENGTH,
        ));

        return self::SUCCESS;
    }

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
