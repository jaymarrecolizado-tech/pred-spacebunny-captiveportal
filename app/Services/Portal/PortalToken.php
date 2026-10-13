<?php

namespace App\Services\Portal;

use App\Models\PortalSession;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Support\Facades\Crypt;

/**
 * The opaque handoff token carried from the gateway to the survey page.
 *
 * Design notes (Spec 9.3):
 *  - Built on Laravel's authenticated encryption. No custom cryptography.
 *  - Short-lived: the expiry is inside the payload and re-checked server-side,
 *    so a leaked URL stops working on its own.
 *  - Bound to one integration, one site, one session and one nonce. Changing
 *    any of those invalidates the token.
 *  - Opaque to the client: nothing readable is exposed, so a tampered URL
 *    cannot be edited into a different site.
 */
class PortalToken
{
    /**
     * @return array<string, mixed>
     */
    public static function payloadFor(PortalSession $session): array
    {
        return [
            'v' => 1,
            'sid' => $session->id,
            'nid' => $session->integration_id,
            'site' => $session->site_id,
            'nonce' => $session->nonce,
            'exp' => $session->expires_at->getTimestamp(),
        ];
    }

    public static function mint(PortalSession $session): string
    {
        return Crypt::encryptString((string) json_encode(self::payloadFor($session)));
    }

    /**
     * Decrypt and structurally validate a token.
     *
     * @return array<string, mixed>|null Null when the token is malformed,
     *                                   tampered with, or expired.
     */
    public static function verify(?string $token): ?array
    {
        if (! is_string($token) || $token === '') {
            return null;
        }

        try {
            $payload = json_decode(Crypt::decryptString($token), true);
        } catch (DecryptException) {
            return null;
        }

        if (! is_array($payload)) {
            return null;
        }

        foreach (['v', 'sid', 'nid', 'site', 'nonce', 'exp'] as $key) {
            if (! array_key_exists($key, $payload)) {
                return null;
            }
        }

        // Expiry is enforced here, not only at session level, so an expired
        // token is rejected even if the session row was tampered with.
        if ((int) $payload['exp'] < now()->getTimestamp()) {
            return null;
        }

        return $payload;
    }
}
