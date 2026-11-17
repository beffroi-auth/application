<?php

declare(strict_types=1);

namespace App\Fido\Conformance;

use Beffroi\Core\Bridge\Webauthn\WebauthnCeremonies;
use Beffroi\Core\Bridge\Webauthn\WebauthnPasskeyRegistrar;
use Beffroi\Core\Domain\Credential\Attestation\AttestationEvidence;
use Beffroi\Core\Domain\Credential\Attestation\VerifiedRegistration;
use Beffroi\Core\Domain\Identity\User;
use Beffroi\Core\Domain\Credential\PasskeyUserHandle;
use Beffroi\Core\Domain\Tenant\Tenant;
use Beffroi\Core\Exception\PasskeyCeremonyFailedException;
use Beffroi\Core\Port\PasskeyRegistrarInterface;
use function sprintf;
use Throwable;
use Webauthn\AuthenticatorAttestationResponse;
use Webauthn\PublicKeyCredentialCreationOptions;

/**
 * FIDO2 server API: the attestation the tools ask for is verified as the library verifies it, metadata and status reports
 * included, and never judged by the policy of the tenant.
 */
final readonly class ConformanceToolsRegistrar implements PasskeyRegistrarInterface
{
    public function __construct(
        private WebauthnPasskeyRegistrar $drawn,
        private WebauthnCeremonies $ceremonies,
    ) {
    }

    public function creationOptions(User $user, PasskeyUserHandle $userHandle, array $existing): PublicKeyCredentialCreationOptions
    {
        return $this->drawn->creationOptions($user, $userHandle, $existing);
    }

    public function verifyAttestation(Tenant $tenant, PublicKeyCredentialCreationOptions $options, string $response): VerifiedRegistration
    {
        $attestation = $this->ceremonies->credentialFromJson($response)->response;
        if (! $attestation instanceof AuthenticatorAttestationResponse) {
            throw new PasskeyCeremonyFailedException('The response of the browser is not the attestation of a new passkey.');
        }
        try {
            $record = $this->ceremonies->attestationValidator($tenant)->check($attestation, $options, WebauthnCeremonies::relyingPartyId($tenant));
        } catch (Throwable $exception) {
            throw new PasskeyCeremonyFailedException(sprintf('The attestation was refused: %s', $exception->getMessage()), previous: $exception);
        }
        $seen = $this->ceremonies->attestation($tenant, $attestation, $record, $options);

        return new VerifiedRegistration($record, $seen->provesTheModel() ? AttestationEvidence::verified() : AttestationEvidence::declared());
    }
}
