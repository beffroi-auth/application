<?php

declare(strict_types=1);

namespace App\Controller;

use App\Http\Account\ReachedOnTheSessionAloneInterface;
use App\Http\AccountAccess;
use App\Http\ArchivePage;
use App\Http\InteractionPages;
use App\Http\RequestContextFactory;
use App\Http\WhatWasRefused;
use Beffroi\Core\Application\Archive\ArchiveDisposal;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;

#[AsController]
final readonly class AccountArchiveDisposalController implements ReachedOnTheSessionAloneInterface
{
    public const string ROUTE = 'beffroi_interaction_account_archive_drop';

    public const string CSRF_TOKEN_ID = 'beffroi_archive_drop';

    public function __construct(
        private AccountAccess $account,
        private RequestContextFactory $contexts,
        private ArchiveDisposal $disposal,
        private WhatWasRefused $refusals,
        private InteractionPages $pages,
    ) {
    }

    #[Route('/account/archive/drop', name: self::ROUTE, methods: ['POST'])]
    public function __invoke(Request $request): Response
    {
        $session = $this->account->granted($request, self::CSRF_TOKEN_ID);
        if ($session instanceof Response) {
            return $session;
        }
        if (! $this->disposal->droppedBy($session->user, $this->contexts->fromRequest($request))) {
            $this->refusals->keep($request, ArchivePage::NOTHING_TO_DROP);
        }

        return $this->pages->afterTheForm(AccountArchiveController::ROUTE);
    }
}
