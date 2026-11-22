<?php

declare(strict_types=1);

namespace App\Mail;

use App\Messenger\NotificationNotDeliveredException;
use Beffroi\Core\Domain\Identity\EmailAddress;
use Beffroi\Core\Domain\Identity\User;
use Beffroi\Core\Domain\Key\MailSigningKey;
use Beffroi\Core\Exception\KmsUnavailableException;
use Beffroi\Core\Exception\RuntimeException as CoreRuntimeException;
use Beffroi\Core\Port\MailSigningKeyRepositoryInterface;
use Psr\Clock\ClockInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Mime\Email;
use Symfony\Component\Mime\Exception\ExceptionInterface as MimeException;
use Symfony\Component\Mime\Message;

/**
 * What a mail becomes before it leaves: signed when the tenant holds a key, in one format, then encrypted to the person
 * who asked for it (RFC 8551 §3.7, RFC 3156 §6.1). The signature is made in the format of the key the person gave when
 * the tenant holds a key of it, otherwise in the first format wired that the tenant holds a key of.
 */
final readonly class MailProtection
{
    /**
     * @param iterable<MailSignerInterface>    $signers    in the order a format signs when the person gave no key
     * @param iterable<MailEncrypterInterface> $encrypters
     */
    public function __construct(
        private iterable $signers,
        private iterable $encrypters,
        private MailSigningKeyRepositoryInterface $keys,
        private OpenedMailSigningKeys $opened,
        private ClockInterface $clock,
        private LoggerInterface $logger,
    ) {
    }

    /**
     * @return Message|null null when the mail is written to nobody: the tenant signs and cannot sign this one, or the
     *                      person asked for it encrypted and it cannot be
     *
     * @throws NotificationNotDeliveredException when the KMS does not open the signing key, which a later attempt may
     */
    public function of(User $user, EmailAddress $sender, Email $mail): ?Message
    {
        $signed = $this->signed($user, $sender, $mail);
        if ($signed === null) {
            return null;
        }

        return $this->encrypted($user, $signed);
    }

    /**
     * NIST SP 800-63B-4 §4.2.1.2, decision 0051: the confirmation of an address, signed as every mail of the tenant and
     * encrypted to no one, since what the person gave names their verified address and not the one it establishes.
     *
     * @return Message|null null when the mail is written to nobody: the tenant signs and cannot sign this one
     *
     * @throws NotificationNotDeliveredException when the KMS does not open the signing key, which a later attempt may
     */
    public function ofAConfirmation(User $user, EmailAddress $sender, Email $mail): ?Message
    {
        return $this->signed($user, $sender, $mail);
    }

    /**
     * @throws NotificationNotDeliveredException
     */
    private function signed(User $user, EmailAddress $sender, Email $mail): ?Message
    {
        [$signer, $key] = $this->signingKey($user);
        if ($signer === null || $key === null) {
            return $mail;
        }
        // A tenant that signs never writes unsigned: a mail of it without its signature is what someone else would write.
        if (! $key->signsFor($sender, $this->clock->now())) {
            $this->logger->error('This tenant signs its mail and its key cannot sign this one: it is past its certificate, or it was set for another sender.', [
                'tenant' => $user->tenant->slug,
                'format' => $key->kind,
                'fingerprint' => $key->fingerprint,
            ]);

            return null;
        }
        try {
            return $signer->sign($mail, $key, $this->opened->privateHalfOf($key));
        } catch (KmsUnavailableException $outage) {
            throw new NotificationNotDeliveredException('The KMS did not open the key the mail is signed with (' . $outage::class . ').');
        } catch (CoreRuntimeException|MimeException $refusal) {
            $this->logger->error('This tenant signs its mail and could not sign this one.', [
                'tenant' => $user->tenant->slug,
                'format' => $key->kind,
                'reason' => $refusal->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * @return array{MailSignerInterface|null, MailSigningKey|null}
     */
    private function signingKey(User $user): array
    {
        $asked = $user->mailEncryption?->kind;
        $first = [null, null];
        foreach ($this->signers as $signer) {
            $key = $this->keys->current($user->tenant, $signer->kind());
            if ($key === null) {
                continue;
            }
            if ($signer->kind() === $asked) {
                return [$signer, $key];
            }
            if ($first[0] === null) {
                $first = [$signer, $key];
            }
        }

        return $first;
    }

    private function encrypted(User $user, Message $mail): ?Message
    {
        $key = $user->mailEncryption;
        if ($key === null) {
            return $mail;
        }
        // Never in clear to a person who asked otherwise: what cannot be encrypted to them is written to nobody.
        if (! $key->isValidAt($this->clock->now())) {
            $this->logger->warning('A person asked for their mail encrypted, and the certificate or the key they gave is not valid now.', [
                'tenant' => $user->tenant->slug,
                'format' => $key->kind,
            ]);

            return null;
        }
        foreach ($this->encrypters as $encrypter) {
            if ($encrypter->kind() !== $key->kind) {
                continue;
            }
            try {
                return $encrypter->encrypt($mail, $key);
            } catch (MimeException $refusal) {
                $this->logger->warning('A person asked for their mail encrypted, and it could not be encrypted to what they gave.', [
                    'tenant' => $user->tenant->slug,
                    'format' => $key->kind,
                    'reason' => $refusal->getMessage(),
                ]);

                return null;
            }
        }
        $this->logger->warning('A person asked for their mail encrypted in a format this deployment does not write.', [
            'tenant' => $user->tenant->slug,
            'format' => $key->kind,
        ]);

        return null;
    }
}
