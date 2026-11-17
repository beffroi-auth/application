<?php

declare(strict_types=1);

namespace App\Saml;

use Beffroi\Core\Bridge\JwtLibrary\Algorithm\RsaKeyBits;
use Beffroi\Core\Bridge\JwtLibrary\JoseKeyAlgorithmInterface;
use Beffroi\Core\Domain\Key\KeyUse;
use Beffroi\Saml\Domain\Key\SamlEncryptionAlgorithmInterface;
use Jose\Component\Core\JWK;
use Jose\Component\KeyManagement\JWKFactory;

/**
 * beffroi/saml S-64
 *
 * One per upstream identity provider: the core keeps one active key per algorithm, so that the key of each
 * provider is rotated apart from the others.
 */
final readonly class SamlEncryption implements JoseKeyAlgorithmInterface, SamlEncryptionAlgorithmInterface
{
    public function __construct(
        private string $identityProvider,
        private RsaKeyBits $keyBits = new RsaKeyBits(),
    ) {
    }

    public function name(): string
    {
        return self::NAME_PREFIX . $this->identityProvider;
    }

    public function keyType(): string
    {
        return 'RSA';
    }

    public function keyUse(): KeyUse
    {
        return KeyUse::Encryption;
    }

    public function identityProvider(): string
    {
        return $this->identityProvider;
    }

    public function generateKey(): JWK
    {
        return JWKFactory::createRSAKey($this->keyBits->value, [
            'alg' => $this->name(),
            'use' => $this->keyUse()->value,
        ]);
    }
}
