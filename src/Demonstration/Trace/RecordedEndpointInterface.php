<?php

declare(strict_types=1);

namespace App\Demonstration\Trace;

use Beffroi\Oidc\Domain\Client\GrantTypeName;
use Beffroi\Oidc\Domain\Discovery\ProviderEndpoints;
use Beffroi\Oidc\Port\ServedGrantsInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

interface RecordedEndpointInterface
{
    public function recognises(Request $request, Response $response, ProviderEndpoints $endpoints): bool;

    public function flowOf(Request $request, ServedGrantsInterface $served): ?GrantTypeName;

    public function clientOf(Request $request): ?string;

    /**
     * @return array{string|null, string|null} the hop the call fills, then the hop its answer fills
     */
    public function hops(): array;
}
