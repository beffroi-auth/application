<?php

declare(strict_types=1);

namespace App\Client;

use App\Http\BoundedRead;
use App\Http\ByteLimit;
use App\Http\ReadRefusedException;
use App\Http\ReadWords;
use App\Http\Timeout;
use function array_is_list;
use Beffroi\Oidc\Domain\Client\ClientUri;
use Beffroi\Oidc\Exception\SectorIdentifierUnavailableException;
use Beffroi\Oidc\Port\SectorIdentifierDocumentFetcherInterface;
use function is_array;
use function is_string;
use function json_decode;
use const JSON_THROW_ON_ERROR;
use function sprintf;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Throwable;

/**
 * OIDC Registration §5
 *
 * No redirect is followed: a redirect would let a third party answer for the host of the sector.
 */
final readonly class SectorIdentifierDocumentFetcher implements SectorIdentifierDocumentFetcherInterface
{
    private BoundedRead $reading;

    public function __construct(
        HttpClientInterface $http,
        Timeout $timeout,
        ByteLimit $maximumSize,
    ) {
        $this->reading = new BoundedRead($http, $timeout, $maximumSize);
    }

    public function fetch(ClientUri $sectorIdentifierUri): array
    {
        $body = $this->read($sectorIdentifierUri);
        try {
            $document = json_decode($body, true, 8, JSON_THROW_ON_ERROR);
        } catch (Throwable $exception) {
            throw new SectorIdentifierUnavailableException(sprintf('%s does not answer JSON.', $sectorIdentifierUri->value), 0, $exception);
        }
        if (! is_array($document) || ! array_is_list($document)) {
            throw new SectorIdentifierUnavailableException(sprintf(
                '%s does not answer the JSON array of redirection URIs of OIDC Registration section 5.',
                $sectorIdentifierUri->value,
            ));
        }
        $redirectUris = [];
        foreach ($document as $value) {
            if (! is_string($value)) {
                throw new SectorIdentifierUnavailableException(sprintf(
                    '%s answers an array holding something that is not a redirection URI (OIDC Registration section 5).',
                    $sectorIdentifierUri->value,
                ));
            }
            $redirectUris[] = $value;
        }

        return $redirectUris;
    }

    /**
     * @throws SectorIdentifierUnavailableException
     */
    private function read(ClientUri $sectorIdentifierUri): string
    {
        try {
            return $this->reading->read($sectorIdentifierUri->value, 'application/json', new ReadWords(
                'publish the document at the URL that is registered.',
                '%s answers more than %d bytes, which no list of redirection URIs needs.',
            ));
        } catch (ReadRefusedException $exception) {
            throw new SectorIdentifierUnavailableException($exception->getMessage(), 0, $exception->getPrevious());
        }
    }
}
