<?php

declare(strict_types=1);

namespace App\Fido\Conformance;

use App\Doctrine\TenantProvider;
use App\Http\RequestContextFactory;
use Beffroi\Core\Application\Passkey\PasskeyEnrolment;
use Beffroi\Core\Bridge\Webauthn\WebauthnCeremonies;
use Beffroi\Core\Exception\CredentialEnrolmentFailedException;
use Beffroi\Core\Exception\PasskeyAttestationRefusedException;
use Beffroi\Core\Exception\PasskeyCeremonyFailedException;
use Beffroi\Core\Port\UserRepositoryInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;

/**
 * FIDO2 server API, ServerAuthenticatorAttestationResponse
 */
#[AsController]
final readonly class AttestationResultController
{
    public const string ROUTE = 'beffroi_fido_conformance_attestation_result';

    public const string PASSKEY_NAME = 'FIDO conformance tools';

    public function __construct(
        private TenantProvider $tenants,
        private UserRepositoryInterface $users,
        private RequestContextFactory $contexts,
        private PasskeyEnrolment $enrolment,
        private WebauthnCeremonies $ceremonies,
        private CeremonyStore $store,
    ) {
    }

    #[Route('/attestation/result', name: self::ROUTE, methods: ['POST'])]
    public function __invoke(Request $request): Response
    {
        $response = $request->getContent();
        try {
            $challenge = $this->ceremonies->credentialFromJson($response)->response->clientDataJSON->challenge;
        } catch (PasskeyCeremonyFailedException $exception) {
            return ServerResponse::failed($exception->getMessage());
        }
        $ceremony = $this->store->takeCreation($challenge);
        if ($ceremony === null) {
            return ServerResponse::failed('No creation options were drawn with this challenge, or they were answered already.');
        }
        [$userId, $options] = $ceremony;
        $user = $this->users->find($userId);
        if ($user === null || ! $user->tenant->equals($this->tenants->provide())) {
            return ServerResponse::failed('The user the options were drawn for is gone.');
        }
        try {
            $this->enrolment->complete($user, self::PASSKEY_NAME, $options, $response, $this->contexts->fromRequest($request));
        } catch (CredentialEnrolmentFailedException|PasskeyAttestationRefusedException $exception) {
            return ServerResponse::failed($exception->getMessage());
        }

        return ServerResponse::ok();
    }
}
