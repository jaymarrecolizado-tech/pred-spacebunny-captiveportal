<?php

namespace App\Models;

use App\Enums\AttributionMethod;
use App\Enums\IntegrationMode;
use App\Enums\IntegrationStatus;
use App\Enums\RecordStatus;
use App\Enums\ScopeType;
use App\Enums\SurveyTriggerMode;
use App\Models\Concerns\Auditable;
use Database\Factories\SiteFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property int $id
 * @property string $site_code
 * @property string $name
 * @property string|null $province
 * @property int $supplier_id
 * @property IntegrationMode $integration_mode
 * @property IntegrationStatus $integration_status
 * @property RecordStatus $status
 */
class Site extends Model
{
    /** @use HasFactory<SiteFactory> */
    use Auditable;

    use HasFactory;
    use SoftDeletes;

    protected $fillable = [
        'omada_site_id',
        'site_code',
        'name',
        'region',
        'province',
        'municipality',
        'address_or_landmark',
        'latitude',
        'longitude',
        'supplier_id',
        'gateway_vendor',
        'gateway_model',
        'integration_mode',
        'integration_status',
        'status',
        // Mass-assignment silently drops anything not listed here, which would
        // leave the per-location survey setting saving without ever sticking.
        'survey_trigger_mode',
        'survey_trigger_days',
        'portal_last_seen_at',
        'commissioned_at',
        'last_integration_test_at',
    ];

    protected function casts(): array
    {
        return [
            'integration_mode' => IntegrationMode::class,
            'integration_status' => IntegrationStatus::class,
            'status' => RecordStatus::class,
            'survey_trigger_mode' => SurveyTriggerMode::class,
            'survey_trigger_days' => 'integer',
            'portal_last_seen_at' => 'datetime',
            'latitude' => 'float',
            'longitude' => 'float',
            'commissioned_at' => 'date',
            'last_integration_test_at' => 'datetime',
        ];
    }

    /**
     * What will actually happen to someone who connects at this location.
     *
     * Deliberately one derived answer rather than three separate columns,
     * because the failure people hit is toggling a switch and seeing nothing
     * happen. This says which of the three reasons that is.
     *
     * @return array{label: string, tone: string, hint: string}
     */
    public function surveyStatus(): array
    {
        if ($this->survey_trigger_mode === SurveyTriggerMode::Never) {
            return [
                'label' => 'Off',
                'tone' => 'gray',
                'hint' => 'Nobody is asked for feedback at this location.',
            ];
        }

        $neverPointedAtUs = $this->portal_last_seen_at === null;

        if ($neverPointedAtUs) {
            return [
                'label' => 'Waiting for setup',
                'tone' => 'warning',
                'hint' => 'Set to ask for feedback, but the controller has never sent anyone here. '
                    .'Point this location at the survey portal in Omada, then it will start by itself.',
            ];
        }

        return [
            'label' => 'Live',
            'tone' => 'success',
            'hint' => $this->survey_trigger_mode === SurveyTriggerMode::Always
                ? 'Every connection is asked to complete the survey.'
                : 'Everyone is asked at least once, then straight through for '
                    .$this->survey_trigger_days.' days.',
        ];
    }

    protected static function booted(): void
    {
        static::updating(function (self $site): void {
            if ($site->isDirty('site_code')) {
                throw new \LogicException('site_code is immutable and cannot be changed.');
            }
        });
    }

    /** @return BelongsTo<Supplier, $this> */
    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    /** @return HasMany<SurveyResponse, $this> */
    public function responses(): HasMany
    {
        return $this->hasMany(SurveyResponse::class);
    }

    /** @return HasMany<SurveyAssignment, $this> */
    public function assignments(): HasMany
    {
        return $this->hasMany(SurveyAssignment::class);
    }

    /** FR-RESP-002: only active sites may accept or display a public survey. */
    public function acceptsResponses(): bool
    {
        return $this->status === RecordStatus::Active
            && $this->integration_status !== IntegrationStatus::Disabled;
    }

    /** @param Builder<self> $query */
    public function scopeWhereCode(Builder $query, string $siteCode): void
    {
        $query->where('site_code', $siteCode);
    }

    /**
     * FR-ADM-002: row-level organizational scope.
     *
     * Every listing, report and export funnels through this scope, and every
     * policy calls the matching `isVisibleTo`, so hiding a menu item is never
     * the only protection. Rows are decided by scope; roles decide actions.
     *
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeVisibleTo(Builder $query, ?User $user): Builder
    {
        if ($user === null || $user->hasFullScope()) {
            return $query;
        }

        if ($user->scope_type === ScopeType::Supplier) {
            return $query->where($this->qualifyColumn('supplier_id'), $user->scope_supplier_id);
        }

        if ($user->scope_type === ScopeType::Province) {
            $provinces = $user->provinceList();

            return $provinces === []
                ? $query->whereRaw('1 = 0')
                : $query->whereIn($this->qualifyColumn('province'), $provinces);
        }

        return $query;
    }

    /**
     * Re-reads the integration's site, so a site disabled after the
     * credentials were checked still stops new sessions (FR-CAP-008).
     */
    public function acceptsRequests(): bool
    {
        return $this->status->acceptsRequests()
            && $this->revoked_at === null
            && $this->site()->whereKey($this->site_id)->where('status', RecordStatus::Active->value)->exists();
    }

    /**
     * Whether this single site is inside the user's organizational scope.
     */
    public function isVisibleTo(?User $user): bool
    {
        if ($user === null || $user->hasFullScope()) {
            return true;
        }

        return match ($user->scope_type) {
            ScopeType::Supplier => (int) $this->supplier_id === (int) $user->scope_supplier_id,
            ScopeType::Province => in_array($this->province, $user->provinceList(), true),
            default => false,
        };
    }

    /**
     * FR-RESP-006/007: attribution comes from the site's registered integration
     * mode, never from a user-editable parameter.
     *
     * A site configured for a captive portal still records unverified
     * attribution until a validated gateway handoff exists (Phase 5); the
     * platform never claims verified connectivity it cannot prove.
     */
    public function publicAttributionMethod(): AttributionMethod
    {
        return $this->integration_mode === IntegrationMode::QrCode
            ? AttributionMethod::QrCode
            : AttributionMethod::SiteLink;
    }
}
