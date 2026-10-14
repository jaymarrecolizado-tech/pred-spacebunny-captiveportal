# Lessons from the Omada External Portal pilot

Everything here was found by testing against the live controller at POP TEST
(site `POP TEST`, SSID `NMS Test AP`, EAP610). TP-Link's documentation is not
always accurate; where reality differed, reality won and the code was changed.

## 1. The handoff timestamp is in seconds, not microseconds

TP-Link doc 13080 documents `t` as `TIME_SINCE_EPOCH` in **microseconds**. The
live controller sends **seconds** (a 10-digit value).

Consequence: dividing by 1,000,000 made every real handoff look 50 years old,
so the adapter refused every genuine portal redirect.

Fix: `OmadaAdapterController::issuedAtSeconds()` accepts seconds, milliseconds
and microseconds by digit count, so a unit change cannot silently refuse all
traffic again.

## 2. `site` is an opaque controller-side ID, not the site name

The redirect carries `site=68e88354bb5fdb13e8af0451`. Never trust it to
identify our site — the adapter binds the site through the **URL path**
(`/portal/omada/{siteCode}`) and ignores that parameter for routing.

## 3. The grant duration must be milliseconds, and must fit in 32 bits

The parameter table says microseconds; the code sample in the same document
passes a variable literally named `$milliseconds`.

Sending 8 hours as microseconds (28,800,000,000) **overflows the controller's
32-bit field**. The controller answered `{"errorCode":0,"msg":"Success."}` and
listed the client under Authorized Clients — but the grant was already expired,
so the client got **zero traffic** and no internet.

How it was caught: the Authorized Clients screen showed every
"External Portal Server" row with DOWNLOAD/UPLOAD of 0, while an older
"No Authentication" client had moved 85 MB. The site clearly had internet; the
external-portal grants simply were not taking effect.

Fix: `portal.auth_duration_ms`, default `3600000` (one hour). Covered by
`OmadaHotspotClientTest::test_the_grant_duration_is_milliseconds_and_32_bit_safe`.

## 4. Login and authorize must share one HTTP session

The authorize call needs the session cookie (`TPEAP_SESSIONID`, or
`TPOMADA_SESSIONID` on controller v5.11+) from the hotspot-operator login,
plus the returned CSRF token in the `Csrf-Token` header.

Creating a fresh HTTP client for each call silently drops the cookie: the
controller answers `200` with its HTML shell instead of JSON, and the adapter
reports failure with no visible cause.

Fix: one `PendingRequest` (and therefore one cookie jar) per authorize attempt.

**Note:** this class of bug is invisible to `Http::fake()` tests, because
fakes do not enforce cookies. Only a live replay caught it.

## 5. The portal server must be reachable before authentication

A not-yet-authorized device could not reach the platform at all
(`ERR_TIMED_OUT`), because the AP blocks pre-auth traffic to unknown hosts.

Fix: Omada **Access Control → Pre-Authentication Access List**, entry
`187.77.150.203/32` (the platform's address, not its hostname — captive devices
may not resolve DNS).

## 6. Redirects inside a captive mini-browser are unreliable

iOS opens captive pages in a restricted CNA window that swallows
`window.location`, `meta refresh`, and even taps on small links.

Mitigation: three mechanisms on the thanks page — JS `location.replace` retried
a few times (the authorization needs a moment to apply), a `meta http-equiv`
refresh, and a full-size tappable button. The page also tells the user plainly:
close this window, your internet is already on.

The countdown **running to completion** is the proof that authorization
succeeded; a stuck counter means the grant failed.

## 7. "Authorized by External Portal Server" is not the same as working

Always compare against a client that actually passed traffic. A row with
DOWNLOAD/UPLOAD of 0 is an authorization that did not take effect, regardless
of what the controller reported.

## 8. Never use a real survey client MAC in diagnostics

Authorizing a real MAC from a script grants internet to a device nobody is
watching. Use a fabricated MAC (`AA-BB-CC-...`) for probes.