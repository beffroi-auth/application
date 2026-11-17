<?php

declare(strict_types=1);

namespace App\Fido\Conformance;

use function array_map;
use Beffroi\Core\Bridge\Webauthn\PasskeyAlgorithms;
use Cose\Algorithm\Algorithm;
use Cose\Algorithm\Manager;
use Cose\Algorithm\Signature\ECDSA\ES384;
use Cose\Algorithm\Signature\ECDSA\ES512;
use Cose\Algorithm\Signature\RSA\PS256;
use Cose\Algorithm\Signature\RSA\PS384;
use Cose\Algorithm\Signature\RSA\PS512;
use Cose\Algorithm\Signature\RSA\RS1;
use Cose\Algorithm\Signature\RSA\RS384;
use Cose\Algorithm\Signature\RSA\RS512;
use Psr\Clock\ClockInterface;
use Webauthn\AttestationStatement\AttestationStatementSupport;
use Webauthn\AttestationStatement\TPMAttestationStatementSupport;
use Webauthn\PublicKeyCredentialParameters;

/**
 * Decision 0033
 *
 * Only the conformance endpoints read this: the server tests require RS1 (Resp-4 P-8, Resp-9 P-2).
 */
final readonly class ConformanceToolsAlgorithms implements PasskeyAlgorithms
{
    public function __construct(
        private PasskeyAlgorithms $provider,
    ) {
    }

    public function parameters(): array
    {
        return [
            ...$this->provider->parameters(),
            ...array_map(
                static fn (Algorithm $algorithm): PublicKeyCredentialParameters => PublicKeyCredentialParameters::createPk($algorithm::identifier()),
                self::algorithms(),
            ),
        ];
    }

    public function verifiers(): Manager
    {
        return $this->provider->verifiers()->add(...self::algorithms());
    }

    public function tpm(ClockInterface $clock): TPMAttestationStatementSupport
    {
        return TPMAttestationStatementSupport::create($clock, acknowledgeInsecureAlgorithm: true);
    }

    public function attestation(AttestationStatementSupport $support): AttestationStatementSupport
    {
        return $support;
    }

    /**
     * @return non-empty-list<Algorithm>
     */
    private static function algorithms(): array
    {
        return [
            ES384::create(),
            RS1::create(acknowledgeInsecureAlgorithm: true),
            ES512::create(),
            RS384::create(),
            RS512::create(),
            PS256::create(),
            PS384::create(),
            PS512::create(),
        ];
    }
}
