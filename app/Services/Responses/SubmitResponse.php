<?php

namespace App\Services\Responses;

use App\Enums\AttributionMethod;
use App\Models\Site;
use App\Models\SurveyResponse;
use App\Models\SurveyVersion;
use App\Services\Surveys\ResolvedSurvey;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * FR-RESP-001..008: the only write path for public feedback.
 *
 * Both the HTML form and the JSON API call this, so validation, deduplication
 * and attribution rules cannot drift apart between the two.
 */
class SubmitResponse
{
    /**
     * @param  array<string, mixed>  $answers
     */
    public function handle(
        Site $site,
        ResolvedSurvey $resolved,
        array $answers,
        AttributionMethod $attribution,
        ?string $idempotencyKey = null,
        ?string $sessionReference = null,
    ): SurveyResponse {
        $clean = $this->validateAnswers($resolved->version, $answers);

        $idempotencyHash = $idempotencyKey === null
            ? null
            : hash('sha256', $idempotencyKey.'|'.$site->id);

        // FR-RESP-004: a retried request returns the original record instead of
        // writing a second one.
        if ($idempotencyHash !== null) {
            $existing = SurveyResponse::query()
                ->where('idempotency_key_hash', $idempotencyHash)
                ->first();

            if ($existing !== null) {
                return $existing;
            }
        }

        return DB::transaction(fn () => SurveyResponse::create([
            'id' => (string) Str::uuid(),
            'site_id' => $site->id,
            // Snapshot: a later supplier transfer must not rewrite history.
            'supplier_id' => $site->supplier_id,
            'survey_version_id' => $resolved->version->id,
            'attribution_method' => $attribution,
            // FR-RESP-008 / Spec 10.7: hashed correlation only, never a raw token.
            'session_reference_hash' => $sessionReference === null
                ? null
                : hash('sha256', $sessionReference),
            'idempotency_key_hash' => $idempotencyHash,
            'answers_json' => $clean,
            'overall_rating' => $this->ratingFrom($resolved->version, $clean),
            'issue_category' => $this->issueCategoryFrom($resolved->version, $clean),
            'submitted_at' => now(),
            'created_at' => now(),
        ]));
    }

    /**
     * Server-side validation of answers against the published schema.
     *
     * Unknown keys are dropped rather than stored, and every accepted value has
     * to exist in the published question definition.
     *
     * @param  array<string, mixed>  $answers
     * @return array<string, mixed>
     */
    public function validateAnswers(SurveyVersion $version, array $answers): array
    {
        $questions = collect($version->questions())->keyBy('key');
        $clean = [];

        foreach ($questions as $key => $question) {
            $type = $question['type'] ?? null;

            if ($type === 'info') {
                continue;
            }

            $value = $answers[$key] ?? null;

            if ($value === null || $value === '') {
                if ($question['required'] ?? false) {
                    $this->fail($key, 'This question is required.');
                }

                continue;
            }

            $clean[$key] = match ($type) {
                'rating' => $this->validateRating($key, $question, $value),
                'single_choice' => $this->validateChoice($key, $question, $value),
                'short_text' => $this->validateText($key, $question, $value),
                default => throw ValidationException::withMessages([
                    "answers.$key" => 'Unsupported question type.',
                ]),
            };
        }

        return $clean;
    }

    /**
     * @param  array<string, mixed>  $question
     */
    private function validateRating(string $key, array $question, mixed $value): int
    {
        $min = (int) ($question['min'] ?? 1);
        $max = (int) ($question['max'] ?? 5);

        if (filter_var($value, FILTER_VALIDATE_INT) === false
            || (int) $value < $min
            || (int) $value > $max) {
            $this->fail($key, "Select a rating between $min and $max.");
        }

        return (int) $value;
    }

    /**
     * @param  array<string, mixed>  $question
     */
    private function validateChoice(string $key, array $question, mixed $value): string
    {
        $options = array_column($question['options'] ?? [], 'value');

        if (! is_string($value) || ! in_array($value, $options, true)) {
            $this->fail($key, 'Select one of the available options.');
        }

        return $value;
    }

    /**
     * @param  array<string, mixed>  $question
     */
    private function validateText(string $key, array $question, mixed $value): string
    {
        $maxLength = (int) ($question['max_length'] ?? 500);

        if (! is_string($value)) {
            $this->fail($key, 'Invalid comment.');
        }

        $trimmed = trim($value);

        if (mb_strlen($trimmed) > $maxLength) {
            $this->fail($key, "Keep your comment under $maxLength characters.");
        }

        // Defence in depth: answers are always rendered escaped, never raw.
        return strip_tags($trimmed);
    }

    /**
     * @param  array<string, mixed>  $answers
     */
    private function ratingFrom(SurveyVersion $version, array $answers): ?int
    {
        $ratingQuestion = $version->ratingQuestion();

        return $ratingQuestion === null ? null : ($answers[$ratingQuestion['key']] ?? null);
    }

    /**
     * @param  array<string, mixed>  $answers
     */
    private function issueCategoryFrom(SurveyVersion $version, array $answers): ?string
    {
        $question = $version->issueCategoryQuestion();

        return $question === null ? null : ($answers[$question['key']] ?? null);
    }

    private function fail(string $key, string $message): never
    {
        throw ValidationException::withMessages(["answers.$key" => $message]);
    }
}
