<?php

declare(strict_types=1);

namespace App\Controller;

use App\Doctrine\TenantProvider;
use App\Http\ClientRegistrationAnswer;
use Beffroi\Oidc\Application\Client\Registration\ClientConfigurationReplacement;
use Beffroi\Oidc\Domain\Client\Registration\RegistrationRequest;
use Beffroi\Oidc\Exception\UnauthorizedBearerRequestException;
use Beffroi\Oidc\Exception\UnreadableClientMetadataException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;

/**
 * RFC 7592 §2.2
 */
#[AsController]
final readonly class ClientConfigurationUpdateController
{
    public const string ROUTE = 'beffroi_protocol_client_configuration_update';

    public function __construct(
        private TenantProvider $tenants,
        private ClientConfigurationReplacement $replacement,
    ) {
    }

    #[Route(ClientConfigurationReadController::PATH, name: self::ROUTE, methods: ['PUT'])]
    public function __invoke(Request $request, string $client_id): Response
    {
        try {
            $response = $this->replacement->replace(
                $this->tenants->provide(),
                $client_id,
                new RegistrationRequest(ClientRegistrationAnswer::members($request), $request->headers->get('Authorization')),
            );
        } catch (UnauthorizedBearerRequestException $exception) {
            return ClientRegistrationAnswer::challenge($exception);
        } catch (UnreadableClientMetadataException $exception) {
            return ClientRegistrationAnswer::refusal($exception);
        }

        return ClientRegistrationAnswer::information($response);
    }
}
