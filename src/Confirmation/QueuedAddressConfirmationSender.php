<?php

declare(strict_types=1);

namespace App\Confirmation;

use App\Message\SendAddressConfirmation;
use Beffroi\Core\Domain\Confirmation\IssuedAddressConfirmation;
use Beffroi\Core\Port\AddressConfirmationSenderInterface;
use Symfony\Component\Messenger\MessageBusInterface;

final readonly class QueuedAddressConfirmationSender implements AddressConfirmationSenderInterface
{
    public function __construct(
        private MessageBusInterface $commandBus,
    ) {
    }

    public function send(IssuedAddressConfirmation $issued): void
    {
        $confirmation = $issued->confirmation;
        $this->commandBus->dispatch(new SendAddressConfirmation(
            $confirmation->tenant,
            $confirmation->user->id,
            $issued->claim,
            $issued->address,
            $confirmation->id,
            $issued->code,
            $issued->value,
            $confirmation->expiresAt,
        ));
    }
}
