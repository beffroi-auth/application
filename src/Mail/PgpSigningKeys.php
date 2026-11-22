<?php

declare(strict_types=1);

namespace App\Mail;

use App\Mail\OpenPgp\OpenPgpPackets;
use App\Mail\OpenPgp\OpenPgpRsaKey;
use Beffroi\Core\Bridge\JwtLibrary\Algorithm\RsaKeyBits;
use Beffroi\Core\Domain\Identity\EmailAddress;
use Beffroi\Core\Domain\Key\MailSigningKey;
use Beffroi\Core\Domain\Tenant\Tenant;
use Beffroi\Core\Exception\InvalidArgumentException;
use Beffroi\Core\Exception\KmsUnavailableException;
use Beffroi\Core\Exception\RuntimeException;
use Beffroi\Core\Port\KeySealerInterface;
use function bin2hex;
use DateTimeImmutable;
use function is_string;
use Jose\Component\KeyManagement\JWKFactory;
use Psr\Clock\ClockInterface;
use function sprintf;
use function strtoupper;

/**
 * The OpenPGP key of the tenant, drawn here when an administrator asks for one, as every key of the tenant is drawn:
 * its private half exists in clear in this process alone and is sealed at once. Its user ID names the tenant and the
 * address the deployment writes from.
 */
final readonly class PgpSigningKeys
{
    public function __construct(
        private KeySealerInterface $sealer,
        private ClockInterface $clock,
        private RsaKeyBits $bits = new RsaKeyBits(),
    ) {
    }

    /**
     * @throws InvalidArgumentException
     * @throws KmsUnavailableException
     * @throws RuntimeException when the KMS does not seal it
     */
    public function draw(Tenant $tenant, EmailAddress $sender): MailSigningKey
    {
        $jwk = [];
        foreach (JWKFactory::createRSAKey($this->bits->value)->all() as $name => $value) {
            if (is_string($value)) {
                $jwk[(string) $name] = $value;
            }
        }
        // The creation time is part of what the fingerprint hashes, so it is written in whole seconds once.
        $now = new DateTimeImmutable('@' . $this->clock->now()->getTimestamp());
        $key = OpenPgpRsaKey::ofJwk($jwk, $now);
        $published = OpenPgpPackets::armor(
            'PUBLIC KEY BLOCK',
            $key->transferablePublicKey(RsaJwk::privateKey($jwk), sprintf('%s <%s>', $tenant->name, $sender->value), $now),
        );

        return new MailSigningKey(
            $tenant,
            PgpMime::KIND,
            strtoupper(bin2hex($key->fingerprint())),
            $published,
            $this->sealer->seal($tenant, $jwk),
            [$sender],
            $now,
        );
    }
}
