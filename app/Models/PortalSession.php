<?php

namespace App\Models;

use App\Enums\PortalSessionState;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One captive-portal handoff: the gateway asked for a survey, the user was sent
 * to a validated session, and the result is recorded here.
 *
 * The browser never grants access. Only the gateway, using its own secret, can
 * read the outcome (Spec 12.3).
 *
 * @property string $id
 * @property int $integration_id
 * @property int $site_id
 * @property PortalSessionState $status
 * @property string|null $redirect_url
 */
class PortalSession extends Model
{
    use HasUuids;

    protected $fillable = [
        'integration_id',
        'site_id',
        'nonce',
        'status',
        'issued_at',
        'expires_at',
        'completed_at',
        'response_id',
        'redirect_url',
        'gateway_session_hash',
        'client_ip_hash',
    ];

    protected function casts(): array
    {
        return [
            'status' => PortalSessionState::class,
            'issued_at' => 'datetime',
            'expires_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Integration, $this> */
    public function integration(): BelongsTo
    {
        return $this->belongsTo(Integration::class);
    }

    /** @return BelongsTo<Site, $this> */
    public function site(): BelongsTo
    {
        return $this->belongsTo(Site::class);
    }

    public function isExpired(): bool
    {
        return $this->expires_at->isPast();
    }

    /**
     * A session may be used while it is active and unexpired.
     */
    public function isUsable(): bool
    {
        return $this->status === PortalSessionState::Active && ! $this->isExpired();
    }

    public function isCompleted(): bool
    {
        return $this->status === PortalSessionState::Completed;
    }
}
