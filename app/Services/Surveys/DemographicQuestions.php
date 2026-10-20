<?php

namespace App\Services\Surveys;

/**
 * The demographic questions: who is answering, and which sector they are in.
 *
 * Defined once, in code, so the seeded survey and the live survey on the
 * server cannot drift apart. Both are generated from this list.
 *
 * Both questions are optional. A captive portal is not the place to force
 * people to reveal themselves, and every one of them has a
 * "Prefer not to say" answer.
 */
final class DemographicQuestions
{
    /**
     * @return array<int, array<string, mixed>>
     */
    public static function questions(): array
    {
        return [
            [
                'key' => 'gender',
                'type' => 'single_choice',
                'label' => 'Gender',
                'help' => 'Optional. It helps us understand who uses the free Wi-Fi.',
                'required' => false,
                // The answer is copied into a column of its own so the report
                // can group by it without reading the answers blob.
                'reports_as' => 'gender',
                'options' => [
                    ['value' => 'male', 'label' => 'Male'],
                    ['value' => 'female', 'label' => 'Female'],
                    ['value' => 'non_binary', 'label' => 'Non-binary'],
                    ['value' => 'self_describe', 'label' => 'Prefer to self-describe'],
                    ['value' => 'prefer_not_to_say', 'label' => 'Prefer not to say'],
                ],
            ],
            [
                'key' => 'gender_detail',
                'type' => 'short_text',
                'label' => 'If you chose "Prefer to self-describe", please tell us how you identify (optional).',
                'help' => 'Leave this blank if you did not choose "Prefer to self-describe".',
                'max_length' => 100,
                // Shown, and stored, only when the option above is chosen.
                'show_when' => ['question' => 'gender', 'value' => 'self_describe'],
            ],
            [
                'key' => 'sector',
                'type' => 'single_choice',
                'label' => 'Which sector do you belong to?',
                'help' => 'Optional. It helps us understand who uses the free Wi-Fi.',
                'required' => false,
                'reports_as' => 'sector',
                'options' => [
                    ['value' => 'government', 'label' => 'Government'],
                    ['value' => 'private', 'label' => 'Private Sector'],
                    ['value' => 'student', 'label' => 'Student'],
                    ['value' => 'self_employed', 'label' => 'Self-Employed'],
                    ['value' => 'farmer_fisherfolk', 'label' => 'Farmer / Fisherfolk'],
                    ['value' => 'unemployed', 'label' => 'Unemployed'],
                    ['value' => 'senior_citizen', 'label' => 'Senior Citizen'],
                    ['value' => 'pwd', 'label' => 'PWD (Persons with Disabilities)'],
                    ['value' => 'other', 'label' => 'Other'],
                    ['value' => 'prefer_not_to_say', 'label' => 'Prefer not to say'],
                ],
            ],
        ];
    }

    /**
     * Option labels for the demographic answers: column first, then the value.
     *
     * Used by the report so a chart says "Farmer / Fisherfolk" and not
     * "farmer_fisherfolk".
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
     *
     * A value the platform does not recognise is still shown, rather than
     * blanked: it came from a real person and hiding it would lose data.
     */
    public static function label(string $column, ?string $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        return self::optionLabels()[$column][$value]
            ?? ucfirst(str_replace('_', ' ', $value));
    }
}
