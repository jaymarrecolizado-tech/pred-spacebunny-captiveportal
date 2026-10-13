<?php

namespace App\Models;

use App\Enums\SurveyVersionStatus;
use App\Models\Concerns\Auditable;
use Database\Factories\SurveyVersionFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * An immutable snapshot of question definitions.
 *
 * @property int $id
 * @property int $survey_id
 * @property int $version_number
 * @property array<string, mixed> $schema_json
 * @property SurveyVersionStatus $status
 */
class SurveyVersion extends Model
{
    /** @use HasFactory<SurveyVersionFactory> */
    use Auditable;

    use HasFactory;

    protected $fillable = [
        'survey_id',
        'version_number',
        'schema_json',
        'privacy_notice_version',
        'status',
        'published_at',
        'published_by',
    ];

    protected function casts(): array
    {
        return [
            'schema_json' => 'array',
            'status' => SurveyVersionStatus::class,
            'published_at' => 'datetime',
        ];
    }

    /**
     * FR-SUR-003: once published, the meaning of historical answers must not
     * change underneath the reports. Editing creates a new version instead.
     */
    protected static function booted(): void
    {
        static::updating(function (self $version): void {
            if ($version->getOriginal('status') !== SurveyVersionStatus::Draft
                && $version->isDirty(['schema_json', 'version_number', 'privacy_notice_version'])) {
                throw new \LogicException('Published survey versions are immutable. Create a new version instead.');
            }
        });

        static::deleting(function (self $version): void {
            if ($version->status !== SurveyVersionStatus::Draft) {
                throw new \LogicException('Published survey versions cannot be deleted.');
            }
        });
    }

    /** @return BelongsTo<Survey, $this> */
    public function survey(): BelongsTo
    {
        return $this->belongsTo(Survey::class);
    }

    /** @return BelongsTo<User, $this> */
    public function publishedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'published_by');
    }

    /** @return HasMany<SurveyResponse, $this> */
    public function responses(): HasMany
    {
        return $this->hasMany(SurveyResponse::class);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function questions(): array
    {
        return array_values($this->schema_json['questions'] ?? []);
    }

    /**
     * The single question that defines the overall rating, if the survey has one.
     *
     * @return array<string, mixed>|null
     */
    public function ratingQuestion(): ?array
    {
        foreach ($this->questions() as $question) {
            if (($question['type'] ?? null) === 'rating') {
                return $question;
            }
        }

        return null;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function issueCategoryQuestion(): ?array
    {
        foreach ($this->questions() as $question) {
            if (($question['type'] ?? null) === 'single_choice' && ($question['reports_as_issue_category'] ?? false)) {
                return $question;
            }
        }

        return null;
    }
}
