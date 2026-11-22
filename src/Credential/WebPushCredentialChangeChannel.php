<?php

declare(strict_types=1);

namespace App\Credential;

use App\Push\PushMessage;
use App\Push\WebPushBroadcast;
use Beffroi\Core\Application\Journal\JournalRetention;
use Beffroi\Core\Domain\Credential\CredentialChange;
use Beffroi\Core\Domain\Identity\User;

/**
 * Worth delivering as long as the journal keeps the act it tells of, which the account page it opens shows.
 */
final readonly class WebPushCredentialChangeChannel implements CredentialChangeChannelInterface
{
    public const string NAME = 'web_push';

    public function __construct(
        private CredentialChangeNotices $notices,
        private WebPushBroadcast $broadcast,
        private JournalRetention $retention,
    ) {
    }

    public function name(): string
    {
        return self::NAME;
    }

    public function tell(User $user, CredentialChange $change): void
    {
        $notice = $this->notices->of($user, $change);
        $this->broadcast->toEveryBrowserOf($user, new PushMessage(
            $notice->title,
            $notice->body,
            $notice->accountPage,
            $this->retention->window->keptUntil($change->occurredAt),
        ));
    }
}
