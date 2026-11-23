<?php

declare(strict_types=1);

namespace App\Http;

use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * What a client is wrapped in when a client, a registrant or a person chooses the URLs it requests.
 */
interface OutboundGuardInterface
{
    public function guard(HttpClientInterface $client): HttpClientInterface;
}
