<?php

declare(strict_types=1);

namespace App\Sms;

use App\Messenger\NotificationNotDeliveredException;
use Beffroi\Core\Domain\Identity\User;
use Closure;
use Psr\Log\LoggerInterface;
use function sprintf;
use Symfony\Component\Notifier\Exception\ExceptionInterface as NotifierException;
use Symfony\Component\Notifier\Exception\TransportExceptionInterface;
use Symfony\Component\Notifier\Message\SmsMessage;
use Symfony\Component\Notifier\Transport\TransportInterface;

/**
 * Texts the number of a person once it is verified, and no other.
 */
final readonly class SmsSender
{
    public const string CHANNEL = 'sms';

    /**
     * @param Closure(): TransportInterface $transport built at the first text, so that a DSN no installed bridge reads
     *                                                 fails the text alone and never the channels beside it
     */
    public function __construct(
        private Closure $transport,
        private LoggerInterface $logger,
    ) {
    }

    /**
     * @return bool false for a person who has no number this sender may text, or when the deployment cannot send a
     *              text at all, and who is sent nothing
     *
     * @throws NotificationNotDeliveredException when the transport did not take the text
     */
    public function send(User $user, string $text): bool
    {
        $verified = $user->verifiedPhoneNumber();
        if ($verified === null) {
            return false;
        }
        try {
            $transport = ($this->transport)();
        } catch (NotifierException $fault) {
            // No later attempt would send it: the deployment names a transport no installed bridge reads.
            $this->logger->error('This deployment sends no text: {reason}', [
                'reason' => $fault->getMessage(),
            ]);

            return false;
        }
        try {
            $transport->send(new SmsMessage($verified, $text));
        } catch (TransportExceptionInterface $refusal) {
            // Never what the provider said: it may write back the number it refused, which the lane keeps in clear.
            throw new NotificationNotDeliveredException(sprintf('The SMS transport did not take the message (%s).', $refusal::class));
        } catch (NotifierException $refusal) {
            $this->logger->error('A text was refused before it left.', [
                'tenant' => $user->tenant->slug,
                'reason' => $refusal::class,
            ]);

            return false;
        }

        return true;
    }
}
