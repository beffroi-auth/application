<?php

declare(strict_types=1);

namespace App\Client;

use App\Http\BoundedRead;
use App\Http\ByteLimit;
use App\Http\ReadRefusedException;
use App\Http\ReadWords;
use App\Http\Timeout;
use Beffroi\Oidc\Domain\Client\ClientJwks;
use Beffroi\Oidc\Domain\Client\ClientUri;
use Beffroi\Oidc\Exception\ClientKeysUnavailableException;
use Beffroi\Oidc\Exception\InvalidArgumentException;
use Beffroi\Oidc\Port\ClientJwksFetcherInterface;
use function is_array;
use function json_decode;
use const JSON_THROW_ON_ERROR;
use function sprintf;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Throwable;

/**
 * Follows no redirect, reads the body up to a bound and times out on its own: a hanging client holds no worker.
 */
final readonly class ClientJwksFetcher implements ClientJwksFetcherInterface
{
    private BoundedRead $reading;

    public function __construct(
        HttpClientInterface $http,
        Timeout $timeout,
        ByteLimit $maximumSize,
    ) {
        $this->reading = new BoundedRead($http, $timeout, $maximumSize);
    }

    public function fetch(ClientUri $jwksUri): ClientJwks
    {
        $body = $this->read($jwksUri);
        try {
            $document = json_decode($body, true, 512, JSON_THROW_ON_ERROR);
        } catch (Throwable $exception) {
            throw new ClientKeysUnavailableException(sprintf('%s does not answer JSON.', $jwksUri->value), 0, $exception);
        }
        if (! is_array($document)) {
            throw new ClientKeysUnavailableException(sprintf('%s does not answer a JWK Set.', $jwksUri->value));
        }
        try {
            return ClientJwks::fromDocument($document);
        } catch (InvalidArgumentException $exception) {
            throw new ClientKeysUnavailableException(sprintf('%s does not answer a JWK Set: %s', $jwksUri->value, $exception->getMessage()), 0, $exception);
        }
    }

    /**
     * @throws ClientKeysUnavailableException
     */
    private function read(ClientUri $jwksUri): string
    {
        try {
            return $this->reading->read($jwksUri->value, 'application/json', new ReadWords(
                'publish the keys at the URL that is registered.',
                '%s answers more than %d bytes, which no JWK Set needs.',
            ));
        } catch (ReadRefusedException $exception) {
            throw new ClientKeysUnavailableException($exception->getMessage(), 0, $exception->getPrevious());
        }
    }
}
