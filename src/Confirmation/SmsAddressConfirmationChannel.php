<?php

declare(strict_types=1);

namespace App\Confirmation;

use App\Locale\UserLocale;
use App\Message\SendAddressConfirmation;
use App\Sms\SmsSender;
use Beffroi\Core\Domain\Confirmation\ConfirmableAddressInterface;
use Beffroi\Core\Domain\Identity\User;

final readonly class SmsAddressConfirmationChannel implements AddressConfirmationChannelInterface
{
    public function __construct(
        private SmsSender $sender,
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
            $this->wording->title($user, $locale) . "\n" . $this->wording->body($user, $confirmation, $locale) . "\n" . $this->wording->link($confirmation),
        );
    }
}
