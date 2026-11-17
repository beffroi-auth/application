<?php

declare(strict_types=1);

namespace App\Controller;

use App\Http\Account\ReachedOnTheSessionAloneInterface;
use App\Http\AccountAccess;
use App\Http\ArchivePage;
use App\Http\InteractionPages;
use App\Http\RequestContextFactory;
use App\Http\RequestParameters;
use App\Http\WhatWasRefused;
use Beffroi\Core\Application\Archive\ArchiveHandout;
use Beffroi\Core\Domain\Archive\ArchivePassword;
use Beffroi\Core\Exception\InvalidArgumentException;
use Beffroi\Core\Port\ArchiveContainerInterface;
use function is_string;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;

/**
 * The passphrase travels in the body of a POST answered with the file: it must reach no URL, log or session.
 * It is judged before the archive is handed out, so a refusal is not an act of the journal.
 */
#[AsController]
final readonly class AccountArchiveWrappedFileController implements ReachedOnTheSessionAloneInterface
{
    public const string ROUTE = 'beffroi_interaction_account_archive_wrapped_file';

    public const string CSRF_TOKEN_ID = 'beffroi_archive_wrap';

    public const string PASSWORD_PARAMETER = 'password';

    public function __construct(
        private AccountAccess $account,
        private ArchiveHandout $handout,
        private ArchiveContainerInterface $container,
        private RequestContextFactory $contexts,
        private WhatWasRefused $refusals,
        private InteractionPages $pages,
    ) {
    }

    #[Route('/account/archive/file', name: self::ROUTE, methods: ['POST'])]
    public function __invoke(Request $request): Response
    {
        $session = $this->account->granted($request, self::CSRF_TOKEN_ID);
        if ($session instanceof Response) {
            return $session;
        }
        $typed = RequestParameters::ofForm($request)[self::PASSWORD_PARAMETER] ?? null;
        try {
            $password = new ArchivePassword(is_string($typed) ? $typed : '');
        } catch (InvalidArgumentException) {
            // Only the refusal is kept, never what was typed nor how far off it was.
            $this->refusals->keep($request, ArchivePage::PASSPHRASE_REFUSED);

            return $this->pages->afterTheForm(AccountArchiveController::ROUTE);
        }
        $archive = $this->handout->givenTo($session->user, $this->contexts->fromRequest($request));
        if ($archive === null || $archive->content === null) {
            throw new NotFoundHttpException('No archive is ready for this person.');
        }

        return $this->pages->download(
            $this->container->wrap($archive->content, $archive->fileName(), $password),
            $this->container->mediaType(),
            $archive->fileName($this->container->extension()),
        );
    }
}
