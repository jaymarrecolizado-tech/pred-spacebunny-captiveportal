<?php

namespace App\Models;

use App\Enums\AttributionMethod;
use Database\Factories\SurveyResponseFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Append-only submission record. There is no updated_at: an accepted answer is
 * never silently rewritten (FR-RESP-012 corrections go through a later,
 * audited workflow).
 *
 * @property string $id
 * @property int $site_id
 * @property int $supplier_id
 * @property int $survey_version_id
 * @property AttributionMethod $attribution_method
 * @property array<string, mixed> $answers_json
 */
class SurveyResponse extends Model
{
    /** @use HasFactory<SurveyResponseFactory> */
    use HasFactory;

    use HasUuids;

    public const CREATED_AT = 'created_at';

    public const UPDATED_AT = null;

    public $timestamps = false;

    protected $fillable = [
        'site_id',
        'supplier_id',
        'survey_version_id',
        'attribution_method',
        'session_reference_hash',
        'idempotency_key_hash',
        'answers_json',
        'overall_rating',
        'issue_category',
        'gender',
        'sector',
        'submitted_at',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'answers_json' => 'array',
            'attribution_method' => AttributionMethod::class,
            'overall_rating' => 'integer',
            'submitted_at' => 'datetime',
            'created_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Site, $this> */
    public function site(): BelongsTo
    {
        return $this->belongsTo(Site::class);
    }

    /** @return BelongsTo<Supplier, $this> */
    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    /** @return BelongsTo<SurveyVersion, $this> */
    public function surveyVersion(): BelongsTo
    {
        return $this->belongsTo(SurveyVersion::class, 'survey_version_id');
    }

    /**
     * FR-REP-005: exports and reports use the same scope as the dashboard.
     * Province scope lives on the site, so resolve through it rather than
     * duplicating the rule here.
     *
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeVisibleTo(Builder $query, ?User $user): Builder
    {
        if ($user === null || $user->hasFullScope()) {
            return $query;
        }

        return $query->whereIn(
            $this->qualifyColumn('site_id'),
            Site::query()->visibleTo($user)->select('id')
        );
    }

    public function isVisibleTo(?User $user): bool
    {
        return $user === null
            || $user->hasFullScope()
            || (bool) $this->site()->whereKey($this->site_id)->visibleTo($user)->exists();
    }
}
