<?php

declare(strict_types=1);

namespace App\Mail;

use Beffroi\Core\Domain\Identity\EmailAddress;
use Beffroi\Core\Domain\Key\MailSigningKey;
use Beffroi\Core\Domain\Tenant\Tenant;
use Beffroi\Core\Exception\InvalidArgumentException;
use Beffroi\Core\Exception\KmsUnavailableException;
use Beffroi\Core\Exception\RuntimeException;
use Beffroi\Core\Port\KeySealerInterface;
use function implode;
use function openssl_pkey_get_details;
use function openssl_pkey_get_private;
use Psr\Clock\ClockInterface;
use SensitiveParameter;
use function strlen;

/**
 * The S/MIME key an administrator gives with its certificate, checked before it signs anything: one private RSA key of
 * 2048 bits at least (RFC 8551 §4.1), the certificate first in what is given and the key its own, valid now, naming the
 * sender address (RFC 8550 §3) and allowed to sign mail (§4.4.2, §4.4.4). The private half is sealed at once.
 */
final readonly class SmimeSigningKeys
{
    /**
     * In octets, for the key and for the certificates each: a chain of a few certificates is a few kilobytes.
     */
    public const int LONGEST = 65_536;

    public function __construct(
        private KeySealerInterface $sealer,
        private ClockInterface $clock,
    ) {
    }

    /**
     * @param string $certificates the certificate of the key first, then the chain above it, in PEM
     *
     * @throws InvalidArgumentException when the key or the certificate is not one this provider signs with
     * @throws KmsUnavailableException
     * @throws RuntimeException when the KMS does not seal it
     */
    public function of(Tenant $tenant, #[SensitiveParameter] string $privateKey, string $certificates, EmailAddress $sender): MailSigningKey
    {
        $blocks = strlen($certificates) > self::LONGEST ? [] : SmimeCertificate::blocksOf($certificates);
        if ($blocks === []) {
            throw new InvalidArgumentException('The certificate of the key is given in PEM, first, before the chain above it.');
        }
        $certificate = SmimeCertificate::of($blocks[0]);
        $jwk = RsaJwk::ofPem(strlen($privateKey) > self::LONGEST ? '' : $privateKey);
        $private = openssl_pkey_get_private($privateKey, '');
        $details = $private === false ? false : openssl_pkey_get_details($private);
        if (! $certificate->hasAStrongEnoughRsaKey() || ($details['key'] ?? null) !== $certificate->publicKey()) {
            throw new InvalidArgumentException('The certificate does not certify this key, or the key is an RSA key under 2048 bits.');
        }
        $now = $this->clock->now();
        if (! $certificate->isValidAt($now)) {
            throw new InvalidArgumentException('The certificate is not valid now.');
        }
        $named = false;
        foreach ($certificate->addresses() as $address) {
            $named = $named || $address->isTheSameMailboxAs($sender);
        }
        if (! $named) {
            throw new InvalidArgumentException('The certificate does not name the address this deployment writes its mail from.');
        }
        if (! $certificate->allows('Digital Signature')) {
            throw new InvalidArgumentException('The certificate does not allow its key to sign mail.');
        }
        $chain = [];
        foreach ($blocks as $index => $block) {
            $chain[] = $index === 0 ? $certificate->pem() : SmimeCertificate::of($block)->pem();
        }

        return new MailSigningKey(
            $tenant,
            Smime::KIND,
            $certificate->fingerprint(),
            implode('', $chain),
            $this->sealer->seal($tenant, $jwk),
            [$sender],
            $now,
            $certificate->notAfter(),
        );
    }
}
