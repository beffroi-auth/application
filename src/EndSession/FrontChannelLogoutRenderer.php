<?php

declare(strict_types=1);

namespace App\EndSession;

use App\Http\InteractionPages;
use Beffroi\Core\Domain\Session\AuthenticationSession;
use Beffroi\Oidc\Application\EndSession\FrontChannelLogout;
use Beffroi\Oidc\Domain\EndSession\FrontChannelLogoutPage;
use Beffroi\Oidc\Domain\EndSession\LogoutChannel;
use Symfony\Component\HttpFoundation\Response;

/**
 * Front-Channel Logout 1.0 §2
 */
final readonly class FrontChannelLogoutRenderer implements LogoutChannelInterface
{
    public const string TEMPLATE = 'front_channel_logout.html.twig';

    public function __construct(
        private FrontChannelLogout $logout,
        private InteractionPages $pages,
    ) {
    }

    public function channel(): LogoutChannel
    {
        return LogoutChannel::FrontChannel;
    }

    /**
     * @param list<AuthenticationSession> $sessions
     */
    public function render(array $sessions, ?string $continueTo): ?Response
    {
        $page = $this->logout->pageFor($sessions, $continueTo);
        if ($page === null) {
            return null;
        }

        return $this->pages->render(
            self::TEMPLATE,
            [
                'frames' => $page->frames,
                'continue_to' => $page->continueTo,
                'wait' => FrontChannelLogoutPage::WAIT,
                'script' => FrontChannelLogoutPage::SCRIPT,
            ],
            headers: [
                'Content-Security-Policy' => $page->contentSecurityPolicy(),
            ],
        );
    }
}
