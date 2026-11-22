<?php

declare(strict_types=1);

namespace App\Message;

use Beffroi\Core\Domain\Credential\CredentialChange;

final readonly class TellUserOfCredentialChange implements Notification
{
    /**
     * @param string $channel the name of the one channel this notice goes through
     */
    public function __construct(
        public CredentialChange $change,
        public string $channel,
    ) {
    }
}
