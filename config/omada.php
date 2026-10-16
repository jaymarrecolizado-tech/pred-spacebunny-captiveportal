<?php

/*
| Omada Open API credentials (read-only).
|
| These live in a config file rather than being read with env() inside a service
| provider, because env() returns null once configuration is cached on the
| server. A config file is read at cache time, so the values survive.
|
| The secret is controller-wide: it can read every location on the network. It
| can only READ - nothing in this application changes gateway configuration.
*/

return [

    'client_id' => env('OMADA_OPENAPI_CLIENT_ID'),

    'client_secret' => env('OMADA_OPENAPI_CLIENT_SECRET'),

    'omadac_id' => env('OMADA_OPENAPI_OMADAC_ID'),

    // The "Interface Access Address" from the controller's Open API screen. It
    // already ends in /openapi.
    'base_url' => env('OMADA_OPENAPI_BASE_URL'),

    // The controller presents its factory self-signed certificate, the same one
    // the working hotspot client already accepts.
    'verify_tls' => (bool) env('OMADA_OPENAPI_VERIFY_TLS', false),

];
