<?php

declare(strict_types=1);

namespace App\Controller;

use App\Http\Account\ReachedOnTheSessionAloneInterface;
use App\Http\AccountAccess;
use App\Http\InteractionPages;
use App\Http\RequestContextFactory;
use Beffroi\Core\Application\Authentication\OtherSessionsTermination;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Reached on the session alone: asking for a proof first would mean typing a password on a browser one fears.
 */
#[AsController]
final readonly class AccountOtherSessionsRevocationController implements ReachedOnTheSessionAloneInterface
{
    public const string ROUTE = 'beffroi_interaction_account_sessions_revoke_others';

    public const string CSRF_TOKEN_ID = 'beffroi_sessions_revoke_others';

    public function __construct(
        private AccountAccess $account,
        private OtherSessionsTermination $termination,
        private RequestContextFactory $contexts,
        private InteractionPages $pages,
    ) {
    }

    #[Route('/account/sessions/revoke-others', name: self::ROUTE, methods: ['POST'])]
    public function __invoke(Request $request): Response
    {
        $session = $this->account->granted($request, self::CSRF_TOKEN_ID);
        if ($session instanceof Response) {
            return $session;
        }
        $this->termination->endEveryOther($session, $this->contexts->fromRequest($request));

        return $this->pages->afterTheForm(AccountSessionsController::ROUTE);
    }
}
