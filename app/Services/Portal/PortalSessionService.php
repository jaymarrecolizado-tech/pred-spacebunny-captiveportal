<?php

namespace App\Services\Portal;

use App\Enums\PortalSessionState;
use App\Models\Integration;
use App\Models\PortalSession;
use App\Models\Site;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * The gateway-facing half of the captive portal contract (Spec 9.2 Mode A,
 * 11.2).
 *
 * Every rule that decides whether a network session may be authorized lives
 * here, and every rule is reachable from one method, so an adapter cannot
 * accidentally implement a weaker check than the contract requires.
 */
class PortalSessionService
{
    /** Short enough that a leaked URL is worthless, long enough for a slow survey. */
    public const TTL_MINUTES = 15;

    private function ttlMinutes(): int
    {
        return max(1, (int) config('portal.session_ttl_minutes', self::TTL_MINUTES));
    }

    /**
     * A gateway hands off a client and expects a survey URL back.
     *
     * @param  array{gateway_session_ref?: string|null}  $attributes
     */
    public function open(Integration $integration, ?Site $site, array $attributes = []): PortalSession
    {
        if ($site !== null && $integration->site_id !== null && $integration->site_id !== $site->id) {
            throw new PortalContractException('site_mismatch', 'This integration is not authorised for that site.', 403);
        }

        $session = DB::transaction(fn () => PortalSession::create([
            'integration_id' => $integration->id,
            'site_id' => ($integration->site_id ?? $site?->id),
            'nonce' => (string) Str::random(48),
            'status' => PortalSessionState::Active,
            'issued_at' => now(),
            'expires_at' => now()->addMinutes($this->ttlMinutes()),
            // Spec 10.7: correlation references are hashed, never stored raw.
            'gateway_session_hash' => isset($attributes['gateway_session_ref']) && $attributes['gateway_session_ref'] !== null
                ? hash('sha256', $attributes['gateway_session_ref'])
                : null,
            'client_ip_hash' => hash('sha256', (string) request()->ip()),
        ]));

        $integration->recordSuccess();

        return $session;
    }

    /**
     * Validate a presented token and return the session it authorises.
     *
     * The token must match the session row on every bound field: a token
     * minted for site A cannot be replayed against site B (FR-CAP-004).
     */
    public function resolve(?string $token, string $sessionReference): ?PortalSession
    {
        $payload = PortalToken::verify($token);

        if ($payload === null || ! hash_equals($sessionReference, (string) $payload['sid'])) {
            return null;
        }

        $session = PortalSession::query()
            ->with(['site', 'integration'])
            ->find($sessionReference);

        if ($session === null) {
            return null;
        }

        // Every binding in the token must still match the stored session.
        if ((int) $payload['site'] !== $session->site_id
            || (int) $payload['nid'] !== $session->integration_id
            || ! hash_equals($session->nonce, (string) $payload['nonce'])) {
            return null;
        }

        if ($session->isExpired() && $session->status === PortalSessionState::Active) {
            $session->update(['status' => PortalSessionState::Expired]);

            return null;
        }

        return $session;
    }

    /**
     * Mark a session complete. Idempotent: a retry returns the existing result
     * instead of granting twice (FR-CAP-008, replay handling).
     */
    public function complete(PortalSession $session, ?string $responseId = null): PortalSession
    {
        if ($session->isCompleted()) {
            return $session;
        }

        if ($session->isExpired()) {
            throw new PortalContractException('session_expired', 'This survey session has expired.', 409);
        }

        $session->update([
            'status' => PortalSessionState::Completed,
            'completed_at' => now(),
            'response_id' => $responseId,
        ]);

        return $session->fresh();
    }

    /**
     * What the gateway reads to decide access. Server-to-server only.
     *
     * @return array<string, mixed>
     */
    public function outcome(PortalSession $session): array
    {
        $completed = $session->isCompleted();

        return [
            'session_reference' => $session->id,
            'status' => $session->status->value,
            'completed' => $completed,
            'completed_at' => $session->completed_at?->toIso8601String(),
            'policy' => $session->integration->is_test_mode ? 'test' : 'optional',
            // Lets a gateway re-verify later without calling back.
            'signature' => $completed
                ? $session->integration->signCompletion($session->id, $session->completed_at->toIso8601String())
                : null,
        ];
    }
}
