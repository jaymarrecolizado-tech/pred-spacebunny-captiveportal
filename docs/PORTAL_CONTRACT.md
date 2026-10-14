# Captive Portal Integration Contract — v1

Vendor-neutral handoff between a Wi-Fi gateway and this platform. Adapters map a
specific gateway's protocol onto this contract; they must not weaken it.

**Status:** implemented and verified against the bundled gateway simulator, and
verified over real HTTP on a LAN address end to end (`init` → browser redirect →
survey → submit → `status`).
**Not yet verified against real hardware** — see "Pilot gate" at the end.

## The forward is three hops

"Does the captive portal forward the user to the platform?" is the question that
decides whether any of this works, and it fails silently. There are three hops,
and each has its own way of breaking:

| # | Hop | Who performs it | What breaks it |
|---|---|---|---|
| 1 | `init` → the gateway receives `survey_url` | gateway, server to server | a URL on a host the phone cannot resolve |
| 2 | gateway redirects the browser to `survey_url` | gateway, HTTP 302 | a relative URL, or one built from the gateway's own Host header |
| 3 | the browser posts the survey back, then is returned to the gateway | browser, then us | a form action on the wrong host, a JS-only form, a missing return URL |

**Every `survey_url` is built from `PORTAL_PUBLIC_URL` (defaults to `APP_URL`),
never from the Host header of the `init` call.** A gateway commonly reaches the
platform over a management VLAN, a private hostname, or the VPS's own loopback
address. A captive device can resolve none of those, so deriving the URL from the
request hands the phone a link it can never open. `tests/Feature/PortalForwardingTest.php`
asserts this explicitly.

Three consequences for deployment:

1. `PORTAL_PUBLIC_URL` must be an address reachable **from inside the walled
   garden**, not from the DICT office network.
2. It must be **plain HTTP** unless a certificate the client device already
   trusts is installed. Portal detection happens before the device trusts
   anything, so an HTTPS-only handoff fails on real phones. The platform does not
   force HTTPS and does not send HSTS.
3. Nothing on the handoff path may require JavaScript or a reachable CDN. The
   survey is a plain HTML form; assets are served from the app itself.

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
{
  "site_code": "POP-TEST",
  "gateway_session_ref": "optional-identifier",
  "redirect_url": "https://gateway.example/release?client=abc"
}
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

`redirect_url` is where the browser is sent once the survey is submitted — use
it, because the gateway still holds the client's session and needs the client to
come back so it can release it. Without it the user finishes the survey and is
left on the platform's thank-you page, still unauthenticated. It is optional,
must be an absolute `http`/`https` URL, and is rejected with `422` otherwise; a
`javascript:`, `data:` or protocol-relative value is never accepted, because the
platform echoes this value back to the browser as a redirect. Only an
authenticated gateway can set it — a form field cannot override it. Set
`PORTAL_ALLOWED_RETURN_HOSTS` to pin it to approved hosts.

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
6. **Redirect to `survey_url` verbatim.** It is absolute and already carries the
   token. Do not rewrite its host, strip its query string, or build your own —
   the address in it is the one your captive clients can reach, which is not
   necessarily the address you called `init` on.
7. **Send `redirect_url`** so the client comes back to you. Releasing a session
   only works if the browser returns.

## Failure states the platform implements

| Situation | Platform behaviour | Expected gateway reaction |
|---|---|---|
| Bad/rotated secret | `401 unauthenticated_integration` | Treat as fatal; alert, do not retry blindly |
| Revoked or disabled integration | `403 integration_not_active` | Treat as fatal |
| Unknown site code | `404 unknown_site` | Fatal; configuration error |
| No survey assigned/active | `409 survey_unavailable` | Allow normal access (no survey to do) |
| Session expired | `409 session_expired` | Allow normal access or restart the handoff |
| Unknown session to `status` | `404 unknown_session` | Treat as not completed |
| Invalid field, incl. a non-`http(s)` `redirect_url` | `422` (Laravel validation envelope, not the `error.code` shape) | Fatal; configuration error |
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
- `redirect_url` is stored server-side and settable only by an authenticated
  gateway, so a browser cannot choose where it is sent next; it is validated as an
  absolute `http(s)` URL before a session is created.

## Pilot gate (do not skip)

Before enabling any policy that gates access, confirm on the actual device and
gateway: redirect works on Android and iOS, completion is verifiable, expiry and
replay behave as above, outage falls open, and QR/link submissions still work as a
fallback. A captive portal that only works in a demo is not finished.

Check the forward first — it is the step that fails without an error message.
On a phone that has joined the SSID and has not been authenticated:

1. Set `PORTAL_PUBLIC_URL` to the address the phone can reach, and confirm the
   phone can load that address in a browser **before** any survey exists.
2. Confirm the gateway's 302 lands on that same host, over `http`, with the
   `token` query string intact. A gateway that rewrites the host sends the phone
   to an address it cannot resolve, and the user just sees a spinner.
3. Complete the survey on the phone and confirm the browser returns to
   `redirect_url` — not to the platform's thank-you page.
4. Confirm `status` reports `completed: true` for that session.
5. Repeat on iOS: its portal detection is stricter than Android's, and an
   untrusted certificate stops the portal from appearing at all.