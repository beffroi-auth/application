<?php

declare(strict_types=1);

namespace App\Fido\Conformance;

use App\Doctrine\TenantProvider;
use App\Http\RequestContextFactory;
use Beffroi\Core\Application\Authentication\AuthenticationFailedException;
use Beffroi\Core\Application\Passkey\PasskeyAuthentication;
use Beffroi\Core\Bridge\Webauthn\WebauthnCeremonies;
use Beffroi\Core\Exception\PasskeyCeremonyFailedException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;

/**
 * FIDO2 server API, ServerAuthenticatorAssertionResponse
 */
#[AsController]
final readonly class AssertionResultController
{
    public const string ROUTE = 'beffroi_fido_conformance_assertion_result';

    public function __construct(
        private TenantProvider $tenants,
        private RequestContextFactory $contexts,
        private PasskeyAuthentication $authentication,
        private WebauthnCeremonies $ceremonies,
        private CeremonyStore $store,
    ) {
    }

    #[Route('/assertion/result', name: self::ROUTE, methods: ['POST'])]
    public function __invoke(Request $request): Response
    {
        $response = $request->getContent();
        try {
            $challenge = $this->ceremonies->credentialFromJson($response)->response->clientDataJSON->challenge;
        } catch (PasskeyCeremonyFailedException $exception) {
            return ServerResponse::failed($exception->getMessage());
        }
        $options = $this->store->takeRequest($challenge);
        if ($options === null) {
            return ServerResponse::failed('No request options were drawn with this challenge, or they were answered already.');
        }
        try {
            $this->authentication->authenticate($this->tenants->provide(), $options, $response, $this->contexts->fromRequest($request));
        } catch (AuthenticationFailedException $exception) {
            return ServerResponse::failed($exception->reason->value);
        }

        return ServerResponse::ok();
    }
}
