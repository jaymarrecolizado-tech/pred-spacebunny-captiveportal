# Captive Portal Integration Contract — v1

Vendor-neutral handoff between a Wi-Fi gateway and this platform. Adapters map a
specific gateway's protocol onto this contract; they must not weaken it.

**Status:** implemented and verified against the bundled gateway simulator.
**Not yet verified against real hardware** — see "Pilot gate" at the end.

## Roles

The platform is **not** an access controller. It collects a survey and records a
result. The gateway alone decides whether to grant network access, using only the
answer this platform returns.

This matters: a redirect to this website cannot, by itself, authorize a Wi-Fi
session. Anything built on this contract must keep the gateway in the decision.

## Endpoints

Base: `/api/v1/integrations/portal`. All are `POST`, JSON, and authenticated with
per-integration credentials in headers:

| Header | Value |
|---|---|
| `X-Integration-Id` | The `public_identifier` issued by DICT |
| `X-Integration-Secret` | The integration secret issued by DICT |

Credentials are **per site/supplier**. There is no shared global secret. Rotate by
issuing a new secret; the old one stops working immediately.

### 1. `init` — open a survey session

```json
{ "site_code": "POP-TEST", "gateway_session_ref": "optional-identifier" }
```

Returns `201`:

```json
{
  "data": {
    "session_reference": "uuid",
    "expires_at": "2026-01-01T12:15:00+00:00",
    "survey_url": "https://feedback.example/portal/session/<uuid>?token=<opaque>",
    "site_code": "POP-TEST",
    "policy": "optional"
  }
}
```

Redirect the user's browser to `survey_url`. Treat the whole URL as opaque.

`gateway_session_ref` is the gateway's own reference for the client (session id,
MAC, etc.). It is stored **hashed** and never returned.

### 2. `status` — ask whether the survey was completed

```json
{ "session_reference": "uuid" }
```

Returns `200`:

```json
{
  "data": {
    "session_reference": "uuid",
    "status": "completed",
    "completed": true,
    "completed_at": "2026-01-01T12:04:11+00:00",
    "policy": "optional",
    "signature": "hmac-sha256..."
  }
}
```

`signature` is derived from the integration secret, so a gateway may re-verify an
outcome later without calling back.

### 3. `complete` — record completion (optional)

For gateways that record completion themselves instead of polling `status`. It is
idempotent: calling it twice returns the same completion time and never grants twice.

## Rules a gateway must follow

1. **Call `status`, then decide.** Never authorize because a browser claims it
   finished a survey. Only this platform's server-to-server answer counts.
2. **Fail open on platform failure.** If the platform times out or is unreachable,
   apply the pre-approved outage policy (normally: allow normal Wi-Fi access). The
   public Wi-Fi service must not be held hostage by the survey service.
3. **Short-lived sessions only.** A survey URL expires (default 15 minutes) and is
   single-use. Do not cache or prefetch it.
4. **Do not log tokens or secrets.** Both appear in URLs and headers; redact them.
5. **Use HTTPS** with a certificate the client device trusts, or portal detection
   will fail on real phones.

## Failure states the platform implements

| Situation | Platform behaviour | Expected gateway reaction |
|---|---|---|
| Bad/rotated secret | `401 unauthenticated_integration` | Treat as fatal; alert, do not retry blindly |
| Revoked or disabled integration | `403 integration_not_active` | Treat as fatal |
| Unknown site code | `404 unknown_site` | Fatal; configuration error |
| No survey assigned/active | `409 survey_unavailable` | Allow normal access (no survey to do) |
| Session expired | `409 session_expired` | Allow normal access or restart the handoff |
| Unknown session to `status` | `404 unknown_session` | Treat as not completed |
| Platform timeout/outage | no response | Fail open per policy |
| Tampered URL | redirect to a neutral "session not valid" page | Normal access continues |

## Security properties

- The handoff token is Laravel authenticated encryption: opaque, tamper-evident,
  and short-lived. No custom cryptography.
- The token is bound to one session, one site, one integration, one nonce, and an
  expiry. Changing any of them invalidates it.
- Raw gateway references and client IPs are hashed before storage; raw portal
  tokens are never stored at all.
- Responses can only be claimed as **verified** attribution via this path, because
  it requires a secret the public browser never possesses.
- A mandatory survey policy is silently downgraded to optional unless the site has a
  verified portal integration, so configuration mistakes can never strand a public
  user behind a survey.

## Pilot gate (do not skip)

Before enabling any policy that gates access, confirm on the actual device and
gateway: redirect works on Android and iOS, completion is verifiable, expiry and
replay behave as above, outage falls open, and QR/link submissions still work as a
fallback. A captive portal that only works in a demo is not finished.