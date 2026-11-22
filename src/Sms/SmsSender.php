<?php

declare(strict_types=1);

namespace App\Sms;

use App\Messenger\NotificationNotDeliveredException;
use Beffroi\Core\Domain\Identity\User;
use function sprintf;
use Symfony\Component\Notifier\Exception\TransportExceptionInterface;
use Symfony\Component\Notifier\Message\SmsMessage;
use Symfony\Component\Notifier\Transport\TransportInterface;

/**
 * Texts the number of a person once it is verified, and no other.
 */
final readonly class SmsSender
{
    public function __construct(
        private TransportInterface $transport,
    ) {
    }

    /**
     * @return bool false for a person who has no number this sender may text, and who is sent nothing
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
            $this->transport->send(new SmsMessage($verified, $text));
        } catch (TransportExceptionInterface $refusal) {
            // Never what the provider said: it may write back the number it refused, which the lane keeps in clear.
            throw new NotificationNotDeliveredException(sprintf('The SMS transport did not take the message (%s).', $refusal::class));
        }

        return true;
    }
}
