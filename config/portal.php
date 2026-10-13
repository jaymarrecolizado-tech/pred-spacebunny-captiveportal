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
    | The gateway simulator proves the contract end to end without hardware. It
    | must never be reachable in production.
    */
    'simulator_enabled' => (bool) env('PORTAL_SIMULATOR_ENABLED', env('APP_ENV') === 'local'),

];
