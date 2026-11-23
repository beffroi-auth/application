<?php

declare(strict_types=1);

namespace App\Controller;

use App\Http\Account\ReachedOnTheSessionAloneInterface;
use App\Http\AccountAccess;
use App\Http\AccountHomePage;
use App\Http\UntoldChange;
use App\Http\LoginTarget;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;

#[AsController]
final readonly class AccountController implements ReachedOnTheSessionAloneInterface
{
    public const string ROUTE = 'beffroi_interaction_account';

    public function __construct(
        private AccountAccess $account,
        private LoginTarget $loginTargets,
        private AccountHomePage $page,
        private UntoldChange $untold,
    ) {
    }

    #[Route('/account', name: self::ROUTE, methods: ['GET'])]
    public function __invoke(Request $request): Response
    {
        $session = $this->account->signedIn($request);
        if ($session === null) {
            return $this->loginTargets->sendToLogin($request->getSession(), self::ROUTE);
        }
        $this->loginTargets->forget($request->getSession());

        return $this->page->render($session, $this->untold->take($request->getSession(), $session));
    }
}
