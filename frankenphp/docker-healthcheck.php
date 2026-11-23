#!/usr/bin/env php
<?php

declare(strict_types=1);

// The health check of the image: the provider is healthy when the site of SERVER_NAME answers a document it serves,
// asked over the loopback address under the host of the issuer. The document is the discovery one, or a script of
// the pages in a process of the interaction plane alone, which answers 404 to the discovery path. A variable is read
// as Symfony reads it, from the environment first, then from what the image compiled of .env.

$compiled = is_file('/app/.env.local.php') ? (require '/app/.env.local.php') : [];
$variable = static function (string $name, string $default) use ($compiled): string {
    $value = getenv($name);
    if ($value === false && is_array($compiled)) {
        $value = $compiled[$name] ?? $default;
    }

    return is_string($value) ? $value : $default;
};

$issuer = parse_url($variable('BEFFROI_ISSUER', ''));
$host = is_array($issuer) && isset($issuer['host']) ? $issuer['host'] : 'localhost';
$authority = is_array($issuer) && isset($issuer['port']) ? $host . ':' . $issuer['port'] : $host;
$proxied = $variable('TLS_TERMINATION', 'caddy') === 'proxy';
$sites = preg_split('/[\s,]+/', trim($variable('SERVER_NAME', 'localhost')));
$port = $proxied ? 80 : 443;
if (is_array($sites) && preg_match('/:(\d+)$/D', $sites[0], $named) === 1) {
    $port = (int) $named[1];
}
$path = $variable('BEFFROI_PLANE', 'provider') === 'interaction' ? '/copy.js' : '/.well-known/openid-configuration';

$headers = ['Host: ' . $authority, 'User-Agent: docker-healthcheck'];
if ($proxied) {
    // What the proxy in front adds, read by Symfony from the loopback address when SYMFONY_TRUSTED_PROXIES names it:
    // without it, the provider sends the request to HTTPS.
    $headers[] = 'X-Forwarded-Proto: https';
}
$context = stream_context_create([
    'http' => ['header' => $headers, 'timeout' => 10, 'ignore_errors' => true, 'follow_location' => 0],
    // The certificate is the one Caddy holds for the host, which its internal CA issues for a local name: what is checked
    // is what PHP answers, over an address that never leaves the container.
    'ssl' => ['peer_name' => $host, 'verify_peer' => false, 'verify_peer_name' => false],
]);
$url = sprintf('%s://127.0.0.1:%d%s', $proxied ? 'http' : 'https', $port, $path);

$body = @file_get_contents($url, false, $context);
$answer = http_get_last_response_headers() ?? [];
$status = $answer[0] ?? error_get_last()['message'] ?? 'no answer';
if (is_string($body) && $body !== '' && preg_match('#^HTTP/\S+ 200\b#', $status) === 1) {
    exit(0);
}

$reason = $body === '' ? $status . ', with no body' : $status;
foreach (array_filter($answer, static fn (string $line): bool => stripos($line, 'Location:') === 0) as $location) {
    $reason .= ', ' . $location;
}
fwrite(STDERR, sprintf("%s (Host: %s): %s\n", $url, $authority, $reason));
exit(1);
