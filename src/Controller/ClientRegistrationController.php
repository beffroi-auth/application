<?php

declare(strict_types=1);

namespace App\Controller;

use App\Doctrine\TenantProvider;
use App\Http\ClientRegistrationAnswer;
use Beffroi\Oidc\Application\Client\Registration\ClientRegistrationHandler;
use Beffroi\Oidc\Domain\Client\Registration\RegistrationRequest;
use Beffroi\Oidc\Exception\ClientRegistrationNotOfferedException;
use Beffroi\Oidc\Exception\UnauthorizedBearerRequestException;
use Beffroi\Oidc\Exception\UnreadableClientMetadataException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;

/**
 * RFC 7591 §3.1, §3.2.1, §3.2.2, RFC 6750 §3
 */
#[AsController]
final readonly class ClientRegistrationController
{
    public const string ROUTE = 'beffroi_protocol_client_registration';

    public function __construct(
        private TenantProvider $tenants,
        private ClientRegistrationHandler $handler,
    ) {
    }

    #[Route('/register', name: self::ROUTE, methods: ['POST'])]
    public function __invoke(Request $request): Response
    {
        try {
            $response = $this->handler->handle(
                $this->tenants->provide(),
                new RegistrationRequest(ClientRegistrationAnswer::members($request), $request->headers->get('Authorization')),
            );
        } catch (ClientRegistrationNotOfferedException $exception) {
            throw new NotFoundHttpException($exception->getMessage(), $exception);
        } catch (UnauthorizedBearerRequestException $exception) {
            return ClientRegistrationAnswer::challenge($exception);
        } catch (UnreadableClientMetadataException $exception) {
            return ClientRegistrationAnswer::refusal($exception);
        }

        return ClientRegistrationAnswer::information($response);
    }
}
