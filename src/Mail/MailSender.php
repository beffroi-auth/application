<?php

declare(strict_types=1);

namespace App\Mail;

use App\Messenger\NotificationNotDeliveredException;
use Beffroi\Core\Domain\Identity\EmailAddress;
use Beffroi\Core\Domain\Identity\User;
use Beffroi\Core\Exception\InvalidArgumentException;
use Closure;
use Psr\Log\LoggerInterface;
use function sprintf;
use Symfony\Component\Mailer\Exception\ExceptionInterface as MailerException;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Symfony\Component\Mailer\Transport\TransportInterface;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;
use Symfony\Component\Mime\Exception\ExceptionInterface as MimeException;

/**
 * Writes to the address of a person once it is verified, and to no other.
 */
final readonly class MailSender
{
    /**
     * @param Closure(): TransportInterface $transport built at the first mail, so that a DSN no installed factory reads
     *                                                 fails the mail alone and never the channels beside it
     * @param ?string $sender an address, alone or behind a name; none writes no mail
     */
    public function __construct(
        private Closure $transport,
        private ?string $sender,
        private MailProtection $protection,
        private LoggerInterface $logger,
    ) {
    }

    /**
     * @return bool false for a person who has no address this sender may write to, when the deployment cannot write
     *              a mail at all, or when the mail cannot be signed or encrypted as it must be, and who is sent nothing
     *
     * @throws NotificationNotDeliveredException when the transport did not take the mail, or the KMS did not open the
     *                                           key it is signed with
     */
    public function send(User $user, string $subject, string $text): bool
    {
        $verified = $user->verifiedEmail();
        if ($verified === null) {
            return false;
        }
        try {
            $recipient = new Address($verified->value);
        } catch (MimeException $refusal) {
            $this->logger->warning('A verified address is not one a mail can carry.', [
                'tenant' => $user->tenant->slug,
                'reason' => $refusal::class,
            ]);

            return false;
        }
        try {
            $transport = ($this->transport)();
            $sender = Address::create($this->sender ?? '');
            $from = new EmailAddress($sender->getAddress());
        } catch (MailerException|MimeException|InvalidArgumentException $fault) {
            // No later attempt would write it: the deployment names a transport or a sender that cannot be.
            $this->logger->error('This deployment writes no mail: {reason}', [
                'reason' => $fault->getMessage(),
            ]);

            return false;
        }
        $mail = new Email()
            ->from($sender)
            ->to($recipient)
            ->subject($subject)
            ->text($text);
        // RFC 3834 §5.2, §2: a process wrote it, and an automatic responder that reads it answers nothing back.
        $mail->getHeaders()->addTextHeader('Auto-Submitted', 'auto-generated');
        $protected = $this->protection->of($user, $from, $mail);
        if ($protected === null) {
            return false;
        }
        try {
            $transport->send($protected);
        } catch (TransportExceptionInterface $refusal) {
            // Never what the server said: it may write back the address it refused, which the lane keeps in clear.
            throw new NotificationNotDeliveredException(sprintf(
                'The mail transport did not take the message (%s, code %d).',
                $refusal::class,
                $refusal->getCode(),
            ));
        }

        return true;
    }
}
