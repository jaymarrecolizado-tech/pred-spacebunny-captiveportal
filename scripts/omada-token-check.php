<?php

/**
 * One-shot Omada OpenAPI token check.
 *
 * Reads .omada.local.env directly and calls the client_credentials token endpoint.
 * It prints the controller's errorCode and msg ONLY - never the secret, never the
 * access token. If any base URL region is tried, the failing ones are reported by
 * name so the right region can be confirmed.
 *
 * Usage: php scripts/omada-token-check.php
 */

$envFile = __DIR__.'/../.omada.local.env';

if (! is_file($envFile)) {
    fwrite(STDERR, "Missing $envFile\n");

    exit(1);
}

$values = [];

foreach (file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
    $line = trim($line);

    if ($line === '' || str_starts_with($line, '#') || ! str_contains($line, '=')) {
        continue;
    }

    [$key, $value] = explode('=', $line, 2);
    $values[trim($key)] = trim($value);
}

$required = ['OMADA_OPENAPI_CLIENT_ID', 'OMADA_OPENAPI_CLIENT_SECRET', 'OMADA_OPENAPI_OMADAC_ID'];

foreach ($required as $key) {
    if (($values[$key] ?? '') === '') {
        fwrite(STDERR, "Missing or blank: {$key}\n");

        exit(1);
    }
}

$secretLength = strlen($values['OMADA_OPENAPI_CLIENT_SECRET']);
echo "secret loaded ({$secretLength} chars)\n";
echo 'omadacId: '.$values['OMADA_OPENAPI_OMADAC_ID']."\n\n";

// Explicit base URL wins; otherwise try the regions in likely order.
$baseUrls = $values['OMADA_OPENAPI_BASE_URL'] !== ''
    ? [$values['OMADA_OPENAPI_BASE_URL']]
    : [
        'ap (Asia Pacific)' => 'https://openapi-aps.omadanetworks.com',
        'eu (Europe)' => 'https://openapi-eu.omadanetworks.com',
        'us (North America)' => 'https://openapi-us.omadanetworks.com',
    ];

foreach ($baseUrls as $label => $baseUrl) {
    $baseUrl = rtrim($baseUrl, '/');

    echo "--- {$label}: {$baseUrl}\n";

    $url = $baseUrl.'/openapi/authorize/token?grant_type=client_credentials';
    $payload = json_encode([
        'omadacId' => $values['OMADA_OPENAPI_OMADAC_ID'],
        'client_id' => $values['OMADA_OPENAPI_CLIENT_ID'],
        'client_secret' => $values['OMADA_OPENAPI_CLIENT_SECRET'],
    ]);

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $payload,
        CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_SSL_VERIFYPEER => true,
    ]);

    $body = curl_exec($ch);
    $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);

    if ($body === false) {
        echo "  transport error: {$error}\n\n";

        continue;
    }

    echo "  HTTP {$status}\n";

    $decoded = json_decode($body, true);

    if (! is_array($decoded)) {
        // Never echo a raw body: it can contain the token on success.
        echo '  non-JSON response, '.strlen($body)." bytes (body withheld)\n\n";

        continue;
    }

    $code = $decoded['errorCode'] ?? '?';
    $msg = $decoded['msg'] ?? '(no msg)';

    echo "  errorCode: {$code}\n";
    echo "  msg:       {$msg}\n";

    if ((int) $code === 0 && isset($decoded['result']['accessToken'])) {
        $token = $decoded['result']['accessToken'];
        $expires = $decoded['result']['expiresIn'] ?? '?';

        // Length only. Never the value.
        echo "  SUCCESS - accessToken obtained (".strlen($token)." chars), expiresIn={$expires}s\n";
        echo "  token value withheld on purpose.\n\n";

        // Stop at the first success rather than spraying requests at every region.
        echo "Use this base URL in .env:\nOMADA_OPENAPI_BASE_URL={$baseUrl}\n";

        exit(0);
    }

    echo "\n";
}

echo "No region authenticated. Check the region, the app mode (must be Client), and the omadacId.\n";

exit(1);
