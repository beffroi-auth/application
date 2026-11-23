<?php

declare(strict_types=1);

namespace App\Controller;

use App\Http\Account\ReachedOnTheSessionAloneInterface;
use App\Http\AccountAccess;
use App\Http\ArchivePage;
use App\Http\InteractionPages;
use App\Http\RequestContextFactory;
use App\Http\WhatWasRefused;
use Beffroi\Core\Application\Archive\ArchiveRequest;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\RateLimiter\RateLimiterFactoryInterface;
use Symfony\Component\Routing\Attribute\Route;

#[AsController]
final readonly class AccountArchiveRequestController implements ReachedOnTheSessionAloneInterface
{
    public const string ROUTE = 'beffroi_interaction_account_archive_ask';

    public const string CSRF_TOKEN_ID = 'beffroi_archive_ask';

    /**
     * Each archive the person asks for is built by the worker from every sealed column of theirs.
     */
    public const string LIMITER = 'beffroi_archive_requests';

    public const int LIMIT = 5;

    public const string INTERVAL = '1 hour';

    public function __construct(
        private AccountAccess $account,
        private ArchiveRequest $request,
        private RequestContextFactory $contexts,
        private WhatWasRefused $refusals,
        private InteractionPages $pages,
        #[Autowire(service: 'limiter.' . self::LIMITER)]
        private RateLimiterFactoryInterface $limiter,
    ) {
    }

    #[Route('/account/archive', name: self::ROUTE, methods: ['POST'])]
    public function __invoke(Request $request): Response
    {
        $session = $this->account->granted($request, self::CSRF_TOKEN_ID);
        if ($session instanceof Response) {
            return $session;
        }
        if ($this->limiter->create($session->user->id->toRfc4122())->consume()->isAccepted()) {
            $this->request->askedBy($session->user, $this->contexts->fromRequest($request));
        } else {
            $this->refusals->keep($request, ArchivePage::TOO_MANY_REQUESTS);
        }

        return $this->pages->afterTheForm(AccountArchiveController::ROUTE);
    }
}
