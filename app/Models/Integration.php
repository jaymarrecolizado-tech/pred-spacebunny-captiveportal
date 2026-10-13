<?php

namespace App\Models;

use App\Enums\IntegrationState;
use App\Enums\RecordStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

/**
 * Per-gateway credentials. There is never a universal shared secret
 * (Spec 12.3): every site/supplier integration holds its own.
 *
 * @property string $public_identifier
 * @property string $secret
 * @property IntegrationState $status
 * @property bool $is_test_mode
 */
class Integration extends Model
{
    protected $fillable = [
        'supplier_id',
        'site_id',
        'integration_type',
        'public_identifier',
        'secret',
        'status',
        'is_test_mode',
        'revoked_at',
    ];

    protected $hidden = [
        'secret',
    ];

    protected function casts(): array
    {
        return [
            // Encrypted at rest, decrypted only where a comparison is needed.
            'secret' => 'encrypted',
            'status' => IntegrationState::class,
            'is_test_mode' => 'boolean',
            'last_success_at' => 'datetime',
            'revoked_at' => 'datetime',
        ];
    }

    public static function generateIdentifier(): string
    {
        return (string) Str::uuid();
    }

    public static function generateSecret(): string
    {
        // URL-safe and long enough that guessing is hopeless.
        return Str::random(64);
    }

    /**
     * Constant-time comparison of a presented secret.
     */
    public function secretMatches(?string $candidate): bool
    {
        return is_string($candidate)
            && $candidate !== ''
            && hash_equals($this->secret, $candidate);
    }

    public function acceptsRequests(): bool
    {
        return $this->status->acceptsRequests()
            && $this->revoked_at === null
            && ($this->site_id === null || $this->site()->whereKey($this->site_id)->where('status', RecordStatus::Active->value)->exists());
    }

    public function recordSuccess(): void
    {
        $this->forceFill(['last_success_at' => now(), 'last_error_code' => null])->saveQuietly();
    }

    public function recordFailure(string $code): void
    {
        $this->forceFill(['last_error_code' => $code])->saveQuietly();
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

    /** @return HasMany<PortalSession, $this> */
    public function sessions(): HasMany
    {
        return $this->hasMany(PortalSession::class);
    }

    /**
     * The value a gateway uses to verify a completion offline. Derived from the
     * secret so it can be recomputed without storing anything new.
     */
    public function signCompletion(string $sessionReference, string $completedAt): string
    {
        return hash_hmac('sha256', $sessionReference.'|'.$completedAt, $this->secret);
    }

    /**
     * Never expose the secret through debug output.
     */
    public function __debugInfo(): array
    {
        return array_merge(parent::__debugInfo(), ['secret' => '[redacted]']);
    }
}
