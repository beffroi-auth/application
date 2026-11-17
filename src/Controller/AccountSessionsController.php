<?php

declare(strict_types=1);

namespace App\Controller;

use App\Http\Account\ReachedOnTheSessionAloneInterface;
use App\Http\AccountAccess;
use App\Http\SessionsPage;
use App\Saml\LogoutChainPages;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;

#[AsController]
final readonly class AccountSessionsController implements ReachedOnTheSessionAloneInterface
{
    public const string ROUTE = 'beffroi_interaction_account_sessions';

    public function __construct(
        private AccountAccess $account,
        private SessionsPage $page,
        private LogoutChainPages $logoutChain,
    ) {
    }

    #[Route('/account/sessions', name: self::ROUTE, methods: ['GET'])]
    public function __invoke(Request $request): Response
    {
        $session = $this->account->granted($request);
        if ($session instanceof Response) {
            return $session;
        }

        $this->logoutChain->mayEnd($request, $session);

        return $this->page->render($session);
    }
}
