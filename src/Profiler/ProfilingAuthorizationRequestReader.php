<?php

declare(strict_types=1);

namespace App\Profiler;

use function array_diff;
use function array_keys;
use App\Http\AuthorizationRequestReaderInterface;
use App\Http\ReceivedAuthorizationRequest;
use App\Http\RequestParameters;
use Beffroi\Core\Domain\Tenant\Tenant;
use function implode;
use Symfony\Component\HttpFoundation\Request;

final readonly class ProfilingAuthorizationRequestReader implements AuthorizationRequestReaderInterface
{
    public function __construct(
        private AuthorizationRequestReaderInterface $reader,
        private OpProfile $profile,
        private PanelValues $values,
    ) {
    }

    public function read(Tenant $tenant, Request $request): ReceivedAuthorizationRequest
    {
        $query = RequestParameters::ofBrowserRequest($request);
        $received = $this->reader->read($tenant, $request);
        $this->profile->record(new Observation('Request', 'Authorization request', [
            'how it came in' => self::howItCameIn($received),
            'the query the browser carried' => $this->values->all($query),
            'the request as the provider read it' => $this->values->all($received->parameters),
            'parameters of the query that were dropped' => self::dropped($query, $received),
        ]));

        return $received;
    }

    private static function howItCameIn(ReceivedAuthorizationRequest $received): string
    {
        if ($received->pushed) {
            return $received->carriedByObject
                ? 'pushed, and the record carries a request object (RFC 9126 section 4, RFC 9101 section 6.1)'
                : 'pushed, read back from the record (RFC 9126 section 4)';
        }

        return $received->carriedByObject
            ? 'carried by a request object, the query kept nothing but client_id (OIDC Core section 6.1)'
            : 'in the query of the browser';
    }

    /**
     * @param array<string, mixed> $query
     */
    private static function dropped(array $query, ReceivedAuthorizationRequest $received): string
    {
        if (! $received->pushed && ! $received->carriedByObject) {
            return 'none: the query is the request';
        }
        $dropped = array_diff(array_keys($query), array_keys($received->parameters));

        return $dropped === [] ? 'none' : implode(' ', $dropped);
    }
}
