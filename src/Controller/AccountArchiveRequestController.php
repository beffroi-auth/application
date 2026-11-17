<?php

declare(strict_types=1);

namespace App\Controller;

use App\Http\Account\ReachedOnTheSessionAloneInterface;
use App\Http\AccountAccess;
use App\Http\InteractionPages;
use App\Http\RequestContextFactory;
use Beffroi\Core\Application\Archive\ArchiveRequest;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;

#[AsController]
final readonly class AccountArchiveRequestController implements ReachedOnTheSessionAloneInterface
{
    public const string ROUTE = 'beffroi_interaction_account_archive_ask';

    public const string CSRF_TOKEN_ID = 'beffroi_archive_ask';

    public function __construct(
        private AccountAccess $account,
        private ArchiveRequest $request,
        private RequestContextFactory $contexts,
        private InteractionPages $pages,
    ) {
    }

    #[Route('/account/archive', name: self::ROUTE, methods: ['POST'])]
    public function __invoke(Request $request): Response
    {
        $session = $this->account->granted($request, self::CSRF_TOKEN_ID);
        if ($session instanceof Response) {
            return $session;
        }
        $this->request->askedBy($session->user, $this->contexts->fromRequest($request));

        return $this->pages->afterTheForm(AccountArchiveController::ROUTE);
    }
}
