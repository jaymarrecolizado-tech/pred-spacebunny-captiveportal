<?php

namespace App\Services\Surveys;

/**
 * The "how was it" question and its optional free-text follow-up.
 *
 * Free-text answers were the noisiest thing in the report: most were "ok",
 * "none" or a wall of random characters, and a single long answer could break
 * the printed page. A dropdown makes the common answers countable, and only
 * people who really want to say something else type anything, so the written
 * comments that remain are worth reading.
 *
 * The follow-up box is deliberately short. Long free text from a captive
 * portal phone is rarely useful, and the server refuses anything longer.
 */
final class FeedbackQuestions
{
    /** The longest personal comment the platform accepts. */
    public const MAX_COMMENT_LENGTH = 240;

    /**
     * @return array<int, array<string, mixed>>
     */
    public static function questions(): array
    {
        return [
            [
                'key' => 'feedback',
                'type' => 'dropdown',
                'label' => 'Overall, how was your experience?',
                'help' => 'One tap is enough. Choose “Something else” if you want to add your own words.',
                'required' => false,
                // Copied into a column of its own, so the report can chart the
                // answers without reading the answers blob.
                'reports_as' => 'feedback',
                'options' => [
                    ['value' => 'satisfied', 'label' => 'Satisfied, thank you'],
                    ['value' => 'neutral', 'label' => 'It was okay'],
                    ['value' => 'dissatisfied', 'label' => 'Not satisfied'],
                    ['value' => 'all_good', 'label' => 'Everything worked well'],
                    ['value' => 'other', 'label' => 'Something else (I will describe it)'],
                ],
            ],
            [
                'key' => 'feedback_other',
                'type' => 'short_text',
                'label' => 'Tell us more (optional)',
                'help' => 'A sentence is plenty. Please keep it under '.self::MAX_COMMENT_LENGTH.' characters.',
                'max_length' => self::MAX_COMMENT_LENGTH,
                'required' => false,
                // Shown, and stored, only when "Something else" is chosen.
                'show_when' => ['question' => 'feedback', 'value' => 'other'],
            ],
        ];
    }

    /**
     * Option labels for the feedback answers: column first, then the value.
     *
     * @return array<string, array<string, string>>
     */
    public static function optionLabels(): array
    {
        $labels = [];

        foreach (self::questions() as $question) {
            $column = $question['reports_as'] ?? null;

            if ($column === null) {
                continue;
            }

            foreach ($question['options'] as $option) {
                $labels[$column][$option['value']] = $option['label'];
            }
        }

        return $labels;
    }

    /**
     * The readable label for one stored answer.
     */
    public static function label(?string $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        return self::optionLabels()['feedback'][$value]
            ?? ucfirst(str_replace('_', ' ', $value));
    }
}
