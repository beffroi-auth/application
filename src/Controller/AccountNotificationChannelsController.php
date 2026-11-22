<?php

declare(strict_types=1);

namespace App\Controller;

use App\Http\Account\ReachedOnTheSessionAloneInterface;
use App\Http\AccountAccess;
use App\Http\AskedChannels;
use App\Http\InteractionPages;
use App\Http\RequestContextFactory;
use Beffroi\Core\Application\Notification\ChannelChoice;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Choosing a channel neither adds nor takes away a way in, and nothing chosen here narrows a change of the credentials:
 * the session is enough.
 */
#[AsController]
final readonly class AccountNotificationChannelsController implements ReachedOnTheSessionAloneInterface
{
    public const string ROUTE = 'beffroi_interaction_account_notifications_channels';

    public const string CSRF_TOKEN_ID = 'beffroi_notification_channels';

    public function __construct(
        private AccountAccess $account,
        private AskedChannels $asked,
        private ChannelChoice $choice,
        private RequestContextFactory $contexts,
        private InteractionPages $pages,
    ) {
    }

    #[Route('/account/notifications/channels', name: self::ROUTE, methods: ['POST'])]
    public function __invoke(Request $request): Response
    {
        $session = $this->account->granted($request, self::CSRF_TOKEN_ID);
        if ($session instanceof Response) {
            return $session;
        }
        $this->choice->choose($session->user, $this->asked->asked($request), $this->contexts->fromRequest($request));

        return $this->pages->afterTheForm(AccountNotificationsController::ROUTE);
    }
}
