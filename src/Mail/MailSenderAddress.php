<?php

declare(strict_types=1);

namespace App\Mail;

use Beffroi\Core\Domain\Identity\EmailAddress;
use Beffroi\Core\Exception\InvalidArgumentException;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Exception\ExceptionInterface;

/**
 * The address the deployment writes its mail from, which a key of the tenant signs for.
 */
final readonly class MailSenderAddress
{
    /**
     * @param ?string $sender an address, alone or behind a name, as the mail section names it
     */
    public function __construct(
        private ?string $sender = null,
    ) {
    }

    public function address(): ?EmailAddress
    {
        if ($this->sender === null) {
            return null;
        }
        try {
            return new EmailAddress(Address::create($this->sender)->getAddress());
        } catch (ExceptionInterface|InvalidArgumentException) {
            return null;
        }
    }
}
