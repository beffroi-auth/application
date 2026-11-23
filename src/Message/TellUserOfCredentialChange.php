<?php

declare(strict_types=1);

namespace App\Message;

use Beffroi\Core\Domain\Credential\CredentialChange;
use Beffroi\Core\Domain\Event\CredentialChangeNotTold;
use Beffroi\Core\Domain\Session\RequestContext;
use DateTimeImmutable;

final readonly class TellUserOfCredentialChange implements Notification, NoticeToAPerson, GivenUpAsAnAct
{
    /**
     * @param string $channel the name of the one channel this notice goes through
     */
    public function __construct(
        public CredentialChange $change,
        public string $channel,
    ) {
    }

    /**
     * NIST SP 800-63B-4 §4.6
     *
     * The worker gives up, not a request: the act comes from no address.
     */
    public function givenUp(DateTimeImmutable $at, string $reason): CredentialChangeNotTold
    {
        return new CredentialChangeNotTold(
            $at,
            $this->change->tenant,
            RequestContext::unknown($at),
            $this->change->subject,
            $this->change->act,
            $this->channel,
            $reason,
        );
    }
}
