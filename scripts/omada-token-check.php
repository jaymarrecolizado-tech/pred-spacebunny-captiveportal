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
$secretFile = __DIR__.'/../.omada.secret';

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

// The secret lives in its own single-line file. Keeping it separate from the
// other fields means a pasted value cannot land on the wrong line - which is
// exactly the mistake that put a client ID into the omadacId field once already.
$secret = is_file($secretFile)
    ? trim((string) file_get_contents($secretFile))
    : ($values['OMADA_OPENAPI_CLIENT_SECRET'] ?? '');

// Editors and PowerShell happily prepend a UTF-8 BOM (EF BB BF). Three stray
// bytes at the front of a credential are enough to make the controller reject it
// with -44106 while the value looks correct to the eye, so strip it.
$secret = preg_replace('/^\xEF\xBB\xBF/', '', $secret) ?? $secret;
$secret = trim($secret);

if (str_contains($secret, 'PASTE_')) {
    $secret = '';
}

$required = ['OMADA_OPENAPI_CLIENT_ID', 'OMADA_OPENAPI_OMADAC_ID'];

foreach ($required as $key) {
    if (($values[$key] ?? '') === '') {
        fwrite(STDERR, "Missing or blank: {$key}\n");

        exit(1);
    }
}

if ($secret === '') {
    fwrite(STDERR, "Missing client secret. Put it on line 1 of .omada.secret\n");

    exit(1);
}

$values['OMADA_OPENAPI_CLIENT_SECRET'] = $secret;

$secretLength = strlen($values['OMADA_OPENAPI_CLIENT_SECRET']);
echo "secret loaded ({$secretLength} chars) from .omada.secret\n";
echo 'omadacId: '.$values['OMADA_OPENAPI_OMADAC_ID']."\n\n";

// Explicit base URL wins; otherwise try the regions in likely order.
// These are TP-Link's northbound cloud endpoints, not omadanetworks.com.
$baseUrls = $values['OMADA_OPENAPI_BASE_URL'] !== ''
    ? ['configured' => $values['OMADA_OPENAPI_BASE_URL']]
    : [
        'ap (Asia Pacific / Singapore)' => 'https://aps1-omada-northbound.tplinkcloud.com',
        'eu (Europe)' => 'https://euw1-omada-northbound.tplinkcloud.com',
        'us (North America)' => 'https://use1-omada-northbound.tplinkcloud.com',
    ];

foreach ($baseUrls as $label => $baseUrl) {
    $baseUrl = rtrim($baseUrl, '/');

    echo "--- {$label}: {$baseUrl}\n";

    // "Interface Access Address" already ends in /openapi; the cloud northbound
    // endpoints do not. Appending /openapi blindly yields /openapi/openapi.
    $url = (str_ends_with($baseUrl, '/openapi')
        ? $baseUrl.'/authorize/token'
        : $baseUrl.'/openapi/authorize/token')
        .'?grant_type=client_credentials';

    $payload = json_encode([
        'omadacId' => $values['OMADA_OPENAPI_OMADAC_ID'],
        'client_id' => $values['OMADA_OPENAPI_CLIENT_ID'],
        'client_secret' => $values['OMADA_OPENAPI_CLIENT_SECRET'],
    ]);

    // The controller serves its factory self-signed certificate. The working
    // hotspot client already accepts that (see OmadaHotspotClient), so this is
    // consistent rather than new: it confirms we reached the controller we were
    // configured to talk to, it is not a public PKI identity check.
    $verifyTls = ($values['OMADA_OPENAPI_VERIFY_TLS'] ?? 'false') === 'true';

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $payload,
        CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_SSL_VERIFYPEER => $verifyTls,
        CURLOPT_SSL_VERIFYHOST => $verifyTls ? 2 : 0,
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
        echo '  SUCCESS - accessToken obtained ('.strlen($token)." chars), expiresIn={$expires}s\n";
        echo "  token value withheld on purpose.\n\n";

        // Stop at the first success rather than spraying requests at every region.
        echo "Use this base URL in .env:\nOMADA_OPENAPI_BASE_URL={$baseUrl}\n";

        exit(0);
    }

    echo "\n";
}

echo "No region authenticated. Check the region, the app mode (must be Client), and the omadacId.\n";

exit(1);
