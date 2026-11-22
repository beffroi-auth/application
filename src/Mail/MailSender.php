<?php

declare(strict_types=1);

namespace App\Mail;

use App\Messenger\NotificationNotDeliveredException;
use Beffroi\Core\Domain\Identity\User;
use Psr\Log\LoggerInterface;
use function sprintf;
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
    public function __construct(
        private TransportInterface $transport,
        private Address $sender,
        private LoggerInterface $logger,
    ) {
    }

    /**
     * @return bool false for a person who has no address this sender may write to, and who is sent nothing
     *
     * @throws NotificationNotDeliveredException when the transport did not take the mail
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
        $mail = new Email()
            ->from($this->sender)
            ->to($recipient)
            ->subject($subject)
            ->text($text);
        // RFC 3834 §5: an autoresponder that reads it answers nothing back.
        $mail->getHeaders()->addTextHeader('Auto-Submitted', 'auto-generated');
        try {
            $this->transport->send($mail);
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
