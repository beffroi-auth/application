<?php

declare(strict_types=1);

namespace App\Controller;

use App\Doctrine\TenantProvider;
use App\Http\ClientRegistrationAnswer;
use Beffroi\Oidc\Application\Client\Registration\ClientConfigurationReader;
use Beffroi\Oidc\Exception\UnauthorizedBearerRequestException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;

/**
 * RFC 7592 §2.1
 */
#[AsController]
final readonly class ClientConfigurationReadController
{
    public const string ROUTE = 'beffroi_protocol_client_configuration_read';

    public const string CLIENT_ID = 'client_id';

    public const string PATH = '/register/{' . self::CLIENT_ID . '}';

    public function __construct(
        private TenantProvider $tenants,
        private ClientConfigurationReader $reader,
    ) {
    }

    #[Route(self::PATH, name: self::ROUTE, methods: ['GET'])]
    public function __invoke(Request $request, string $client_id): Response
    {
        try {
            $response = $this->reader->read($this->tenants->provide(), $client_id, $request->headers->get('Authorization'));
        } catch (UnauthorizedBearerRequestException $exception) {
            return ClientRegistrationAnswer::challenge($exception);
        }

        return ClientRegistrationAnswer::information($response);
    }
}
