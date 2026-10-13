<?php

namespace App\Models;

use App\Enums\AssignmentPolicy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $survey_id
 * @property int|null $site_id
 * @property AssignmentPolicy $policy
 * @property string $status
 */
class SurveyAssignment extends Model
{
    protected $fillable = [
        'survey_id',
        'site_id',
        'starts_at',
        'ends_at',
        'policy',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'policy' => AssignmentPolicy::class,
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Survey, $this> */
    public function survey(): BelongsTo
    {
        return $this->belongsTo(Survey::class);
    }

    /** @return BelongsTo<Site, $this> */
    public function site(): BelongsTo
    {
        return $this->belongsTo(Site::class);
    }

    public function isWithinSchedule(?\DateTimeInterface $now = null): bool
    {
        $now ??= now();
        $startsInFuture = $this->starts_at !== null && $this->starts_at->isFuture();
        $ended = $this->ends_at !== null && $this->ends_at->isPast();

        return ! $startsInFuture && ! $ended;
    }
}
