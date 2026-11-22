<?php

declare(strict_types=1);

namespace App\Mail;

use Beffroi\Core\Domain\Key\MailSigningKey;
use Beffroi\Core\Domain\Tenant\Tenant;
use Beffroi\Core\Exception\KmsUnavailableException;
use Beffroi\Core\Exception\RuntimeException;
use Beffroi\Core\Port\KeySealerInterface;
use Beffroi\Core\Port\MailSigningKeyRepositoryInterface;
use Beffroi\Core\Port\OpenableKeysInterface;

/**
 * The private half of the current mail signing key of each format, opened by the KMS once per process and kept while it
 * is the current one (decision 0044): a key it replaced is forgotten the first time its successor is opened.
 */
final class OpenedMailSigningKeys implements OpenableKeysInterface
{
    /**
     * @var array<string, array{string, array<string, string>}> by tenant and format: the fingerprint and the private JWK
     */
    private array $opened = [];

    /**
     * @param iterable<MailSignerInterface> $signers
     */
    public function __construct(
        private readonly KeySealerInterface $sealer,
        private readonly MailSigningKeyRepositoryInterface $keys,
        private readonly iterable $signers,
    ) {
    }

    /**
     * @throws KmsUnavailableException
     * @throws RuntimeException when the envelope is refused
     *
     * @return array<string, string>
     */
    public function privateHalfOf(MailSigningKey $key): array
    {
        $name = $key->tenant->id->toRfc4122() . '/' . $key->kind;
        $held = $this->opened[$name] ?? null;
        if ($held !== null && $held[0] === $key->fingerprint) {
            return $held[1];
        }
        unset($this->opened[$name]);
        $jwk = $this->sealer->unseal($key->tenant, $key->sealedPrivateJwk);
        $this->opened[$name] = [$key->fingerprint, $jwk];

        return $jwk;
    }

    public function open(Tenant $tenant): void
    {
        foreach ($this->signers as $signer) {
            $key = $this->keys->current($tenant, $signer->kind());
            if ($key !== null) {
                $this->privateHalfOf($key);
            }
        }
    }
}
