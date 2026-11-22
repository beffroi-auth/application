<?php

declare(strict_types=1);

namespace App\Confirmation;

use App\Locale\UserLocale;
use App\Mail\MailSender;
use App\Message\SendAddressConfirmation;
use Beffroi\Core\Domain\Confirmation\ConfirmableAddressInterface;
use Beffroi\Core\Domain\Identity\User;

final readonly class MailAddressConfirmationChannel implements AddressConfirmationChannelInterface
{
    public function __construct(
        private MailSender $sender,
        private AddressConfirmationWording $wording,
        private UserLocale $locales,
        private ConfirmableAddressInterface $kind,
    ) {
    }

    public function claim(): string
    {
        return $this->kind->claim();
    }

    public function send(User $user, SendAddressConfirmation $confirmation): void
    {
        $locale = $this->locales->of($user);
        $this->sender->sendTheConfirmation(
            $user,
            $confirmation->address,
            $this->wording->subject($user, $locale),
            $this->wording->body($user, $confirmation, $locale) . "\n\n" . $this->wording->link($confirmation) . "\n",
        );
    }
}
