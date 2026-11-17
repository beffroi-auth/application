<?php

declare(strict_types=1);

namespace App\Saml;

use Beffroi\Core\Bridge\Algorithm\WiredAlgorithms;
use Beffroi\Core\Bridge\JwtLibrary\JoseAlgorithmRegistryInterface;
use Beffroi\Core\Bridge\JwtLibrary\JoseKeyAlgorithmInterface;
use Beffroi\Core\Exception\UnsupportedAlgorithmException;
use Beffroi\Saml\Domain\Key\SamlSigningAlgorithmInterface;

final readonly class JoseSamlSigningAlgorithms implements JoseAlgorithmRegistryInterface
{
    public const string TAG = 'beffroi.saml_signing_algorithm';

    /**
     * @var WiredAlgorithms<JoseKeyAlgorithmInterface&SamlSigningAlgorithmInterface>
     */
    private WiredAlgorithms $wired;

    /**
     * @param iterable<JoseKeyAlgorithmInterface&SamlSigningAlgorithmInterface> $algorithms
     */
    public function __construct(iterable $algorithms)
    {
        $this->wired = new WiredAlgorithms($algorithms);
    }

    /**
     * @return list<JoseKeyAlgorithmInterface&SamlSigningAlgorithmInterface>
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
            'This provider signs no SAML message with a key named "%s"; it signs them with %s.',
            'This provider signs no SAML message with a key named "%s"; it serves no SAML.',
        );
    }
}
