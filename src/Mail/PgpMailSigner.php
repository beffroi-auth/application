<?php

declare(strict_types=1);

namespace App\Mail;

use App\Mail\OpenPgp\OpenPgpPackets;
use App\Mail\OpenPgp\OpenPgpRsaKey;
use App\Mail\OpenPgp\PgpSignaturePart;
use App\Mail\OpenPgp\PgpSignedPart;
use Beffroi\Core\Domain\Key\MailSigningKey;
use Beffroi\Core\Exception\InvalidArgumentException;
use Psr\Clock\ClockInterface;
use SensitiveParameter;
use Symfony\Component\Mime\Exception\RuntimeException;
use Symfony\Component\Mime\Message;

/**
 * RFC 3156 §5: multipart/signed, the body as it is sent first and its detached signature second, application/pgp-
 * signature, written here over the key of the tenant opened in memory: no gpg and no agent ever holds it.
 */
final readonly class PgpMailSigner implements MailSignerInterface
{
    public function __construct(
        private ClockInterface $clock,
    ) {
    }

    public function kind(): string
    {
        return PgpMime::KIND;
    }

    public function sign(Message $mail, MailSigningKey $key, #[SensitiveParameter] array $privateJwk): Message
    {
        $body = $mail->getBody();
        if ($body === null) {
            throw new RuntimeException('A mail without a body is not signed.');
        }
        try {
            $signature = OpenPgpRsaKey::signingAs($key->fingerprint)->detachedTextSignature(
                RsaJwk::privateKey($privateJwk),
                $body->toString(),
                $this->clock->now(),
            );
        } catch (InvalidArgumentException $refusal) {
            throw new RuntimeException($refusal->getMessage(), 0, $refusal);
        }

        return new Message($mail->getHeaders(), new PgpSignedPart($body, new PgpSignaturePart(OpenPgpPackets::armor('SIGNATURE', $signature))));
    }
}
