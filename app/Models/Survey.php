<?php

namespace App\Models;

use App\Enums\SurveyStatus;
use App\Models\Concerns\Auditable;
use Database\Factories\SurveyFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property string $name
 * @property SurveyStatus $status
 * @property int|null $published_version_id
 */
class Survey extends Model
{
    /** @use HasFactory<SurveyFactory> */
    use Auditable;

    use HasFactory;

    protected $fillable = [
        'name',
        'description',
        'status',
        'scope_type',
        'published_version_id',
    ];

    protected function casts(): array
    {
        return [
            'status' => SurveyStatus::class,
        ];
    }

    /** @return HasMany<SurveyVersion, $this> */
    public function versions(): HasMany
    {
        return $this->hasMany(SurveyVersion::class);
    }

    /** @return BelongsTo<SurveyVersion, $this> */
    public function publishedVersion(): BelongsTo
    {
        return $this->belongsTo(SurveyVersion::class, 'published_version_id');
    }

    /** @return HasMany<SurveyAssignment, $this> */
    public function assignments(): HasMany
    {
        return $this->hasMany(SurveyAssignment::class);
    }

    /** @return HasMany<SurveyResponse, $this> */
    public function responses(): HasMany
    {
        return $this->hasMany(SurveyResponse::class, 'survey_version_id', 'published_version_id');
    }

    public function acceptsResponses(): bool
    {
        return $this->status->acceptsResponses() && $this->published_version_id !== null;
    }
}
