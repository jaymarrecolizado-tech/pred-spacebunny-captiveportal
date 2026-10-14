<?php

return [

    /*
    |---------------------------------------------------------------------------
    | Captive portal contract
    |---------------------------------------------------------------------------
    |
    | Gateway handoff settings. The token TTL is deliberately short: it is the
    | window in which a leaked survey URL would still be usable.
    |
    */

    'session_ttl_minutes' => (int) env('PORTAL_SESSION_TTL_MINUTES', 15),

    /*
    | The address the survey must be handed off to. This is NOT the address the
    | gateway calls us on: a gateway often reaches the platform over a management
    | VLAN or private DNS, and a captive device can resolve none of those. Every
    | survey URL is built from this value, so it must be the host that is
    | reachable from inside the walled garden, over plain HTTP, on the port the
    | device will actually use.
    |
    | Defaults to APP_URL. Set it explicitly if the two differ.
    */
    'public_url' => env('PORTAL_PUBLIC_URL', env('APP_URL')),

    /*
    | Optional allow-list of hosts the gateway may name as the place to send the
    | browser back to after the survey. Empty means "any http(s) host", which is
    | acceptable while gateways are DICT-registered; lock it down before the
    | pilot leaves test mode.
    */
    'allowed_return_hosts' => array_values(array_filter(array_map(
        'trim',
        explode(',', (string) env('PORTAL_ALLOWED_RETURN_HOSTS', '')),
    ))),

    /*
    | The gateway simulator proves the contract end to end without hardware. It
    | must never be reachable in production.
    */
    'simulator_enabled' => (bool) env('PORTAL_SIMULATOR_ENABLED', env('APP_ENV') === 'local'),

    /*
    | Authorization duration granted to a client that completes the survey,
    | in microseconds (TP-Link doc 13080). Default: 8 hours.
    */
    'auth_duration_microseconds' => (int) env('PORTAL_AUTH_DURATION_US', 8 * 3600 * 1000000),

    /*
    | Omada hotspot operator used for the authorize-back call. This is the
    | operator account created in the controller's hotspot manager — not the
    | controller admin and not the OpenAPI client credentials. The controller
    | ID is the 32-hex segment in the controller's own URL.
    */
    'omada' => [
        'controller_url' => env('OMADA_CONTROLLER_URL'),
        'controller_id' => env('OMADA_CONTROLLER_ID'),
        'operator_username' => env('OMADA_HOTSPOT_OPERATOR'),
        'operator_password' => env('OMADA_HOTSPOT_PASSWORD'),
    ],

];
