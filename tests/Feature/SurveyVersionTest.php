<?php

namespace Tests\Feature;

use App\Enums\SurveyVersionStatus;
use App\Models\SurveyVersion;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use LogicException;
use Tests\TestCase;

/**
 * FR-SUR-003: publishing freezes a version so historical answers keep meaning.
 */
class SurveyVersionTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_published_version_cannot_be_edited(): void
    {
        $version = SurveyVersion::factory()->create(['status' => SurveyVersionStatus::Published]);

        $this->expectException(LogicException::class);

        $version->update(['schema_json' => ['questions' => []]]);
    }

    public function test_a_published_version_cannot_be_deleted(): void
    {
        $version = SurveyVersion::factory()->create(['status' => SurveyVersionStatus::Published]);

        $this->expectException(LogicException::class);

        $version->delete();
    }

    public function test_a_draft_version_stays_editable(): void
    {
        $version = SurveyVersion::factory()->create(['status' => SurveyVersionStatus::Draft]);

        $version->update(['schema_json' => ['questions' => []]]);

        $this->assertSame([], $version->fresh()->questions());
    }

    public function test_version_numbers_are_unique_per_survey(): void
    {
        $version = SurveyVersion::factory()->create(['version_number' => 1]);

        $this->expectException(QueryException::class);

        SurveyVersion::factory()->create([
            'survey_id' => $version->survey_id,
            'version_number' => 1,
        ]);
    }
}
