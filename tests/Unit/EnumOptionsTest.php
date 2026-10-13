<?php

namespace Tests\Unit;

use App\Enums\AssignmentPolicy;
use App\Enums\AttributionMethod;
use App\Enums\IntegrationMode;
use App\Enums\IntegrationStatus;
use App\Enums\RecordStatus;
use App\Enums\ScopeType;
use App\Enums\SurveyStatus;
use App\Enums\SurveyVersionStatus;
use App\Enums\UserRole;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Every enum feeds a Filament Select. An options() helper that silently returns
 * [] renders empty dropdowns, so the labels must survive.
 */
class EnumOptionsTest extends TestCase
{
    /**
     * @return array<string, array{class-string}>
     */
    public static function enums(): array
    {
        return [
            'record status' => [RecordStatus::class],
            'integration mode' => [IntegrationMode::class],
            'integration status' => [IntegrationStatus::class],
            'survey status' => [SurveyStatus::class],
            'survey version status' => [SurveyVersionStatus::class],
            'assignment policy' => [AssignmentPolicy::class],
            'attribution method' => [AttributionMethod::class],
            'user role' => [UserRole::class],
            'scope type' => [ScopeType::class],
        ];
    }

    /**
     * @param  class-string  $enum
     */
    #[DataProvider('enums')]
    public function test_options_are_keyed_by_value_and_labelled(string $enum): void
    {
        $options = $enum::options();

        $this->assertNotEmpty($options, "{$enum}::options() returned nothing, which renders an empty dropdown.");

        foreach ($enum::cases() as $case) {
            $this->assertArrayHasKey($case->value, $options);
            $this->assertNotSame('', trim((string) $options[$case->value]));
        }
    }
}
