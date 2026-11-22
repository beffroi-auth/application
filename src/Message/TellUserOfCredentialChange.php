<?php

declare(strict_types=1);

namespace App\Message;

use Beffroi\Core\Domain\Credential\CredentialChange;

final readonly class TellUserOfCredentialChange implements Notification
{
    public function __construct(
        public CredentialChange $change,
    ) {
    }
}
