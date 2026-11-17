<?php

declare(strict_types=1);

namespace App\Saml;

use Beffroi\Core\Bridge\Algorithm\WiredAlgorithms;
use Beffroi\Core\Bridge\JwtLibrary\JoseAlgorithmRegistryInterface;
use Beffroi\Core\Bridge\JwtLibrary\JoseKeyAlgorithmInterface;
use Beffroi\Core\Exception\UnsupportedAlgorithmException;
use Beffroi\Saml\Domain\Key\SamlEncryptionAlgorithmInterface;

final readonly class JoseSamlEncryptionAlgorithms implements JoseAlgorithmRegistryInterface
{
    public const string TAG = 'beffroi.saml_encryption_algorithm';

    /**
     * @var WiredAlgorithms<JoseKeyAlgorithmInterface&SamlEncryptionAlgorithmInterface>
     */
    private WiredAlgorithms $wired;

    /**
     * @param iterable<JoseKeyAlgorithmInterface&SamlEncryptionAlgorithmInterface> $algorithms
     */
    public function __construct(iterable $algorithms)
    {
        $this->wired = new WiredAlgorithms($algorithms);
    }

    /**
     * @return list<JoseKeyAlgorithmInterface&SamlEncryptionAlgorithmInterface>
     */
    public function all(): array
    {
        return $this->wired->algorithms;
    }

    public function find(string $name): ?JoseKeyAlgorithmInterface
    {
        return $this->wired->named($name);
    }

    public function refusalOf(string $name): UnsupportedAlgorithmException
    {
        return $this->wired->refusalOf(
            $name,
            'No SAML identity provider encrypts for a key named "%s"; those that do encrypt for %s.',
            'No SAML identity provider encrypts for a key named "%s"; none is declared.',
        );
    }
}
