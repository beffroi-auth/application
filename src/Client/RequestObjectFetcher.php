<?php

declare(strict_types=1);

namespace App\Client;

use App\Http\BoundedRead;
use App\Http\ByteLimit;
use App\Http\ReadRefusedException;
use App\Http\ReadWords;
use App\Http\Timeout;
use Beffroi\Oidc\Domain\Client\ClientUri;
use Beffroi\Oidc\Exception\RequestObjectUnavailableException;
use Beffroi\Oidc\Port\RequestObjectFetcherInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * RFC 9101 §5.2, OIDC Core §6.2
 *
 * Follows no redirect, reads the body up to a bound and times out on its own: a hanging client holds no worker.
 */
final readonly class RequestObjectFetcher implements RequestObjectFetcherInterface
{
    private BoundedRead $reading;

    public function __construct(
        HttpClientInterface $http,
        Timeout $timeout,
        ByteLimit $maximumSize,
    ) {
        $this->reading = new BoundedRead($http, $timeout, $maximumSize);
    }

    public function fetch(ClientUri $requestUri): string
    {
        try {
            return $this->reading->read($requestUri->value, 'application/oauth-authz-req+jwt, application/jwt', new ReadWords(
                'publish the request object at the URL that is registered.',
                '%s answers more than %d bytes, which no request object needs.',
            ));
        } catch (ReadRefusedException $exception) {
            throw new RequestObjectUnavailableException($exception->getMessage(), 0, $exception->getPrevious());
        }
    }
}
