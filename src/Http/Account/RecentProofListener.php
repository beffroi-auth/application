<?php

declare(strict_types=1);

namespace App\Http\Account;

use App\Http\AccountAccess;
use App\Http\LoginTarget;
use App\Http\RecentLogin;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ControllerEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Sends to the login a page that asks for a recent proof when the newest proof of the session is too old.
 *
 * Here and not in the pages, so that the GET and the POST of a page are both guarded.
 */
#[AsEventListener(event: KernelEvents::CONTROLLER)]
final readonly class RecentProofListener
{
    public function __construct(
        private AccountAccess $account,
        private RecentLogin $recent,
        private LoginTarget $loginTargets,
    ) {
    }

    public function __invoke(ControllerEvent $event): void
    {
        $page = $event->getController();
        if (! $event->isMainRequest() || ! $page instanceof AsksForARecentProofInterface) {
            return;
        }
        $request = $event->getRequest();
        $session = $this->account->signedIn($request);
        if ($session === null) {
            return;
        }
        $login = $this->recent->orSendToLogin($request, $session, $page->comesBackTo());
        if ($login === null) {
            $this->loginTargets->forgetIfItIsWaitingFor($request->getSession(), $page->comesBackTo());

            return;
        }
        $event->setController(static fn (): Response => $login);
    }
}
