<?php

declare(strict_types=1);

namespace App\Controller;

use App\Http\Account\ReachedOnTheSessionAloneInterface;
use App\Http\AccountAccess;
use App\Http\InteractionPages;
use App\Http\RequestContextFactory;
use Beffroi\Core\Application\Archive\ArchiveHandout;
use Beffroi\Core\Domain\Archive\PersonalArchive;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;

#[AsController]
final readonly class AccountArchiveFileController implements ReachedOnTheSessionAloneInterface
{
    public const string ROUTE = 'beffroi_interaction_account_archive_file';

    public function __construct(
        private AccountAccess $account,
        private ArchiveHandout $handout,
        private RequestContextFactory $contexts,
        private InteractionPages $pages,
    ) {
    }

    #[Route('/account/archive/file', name: self::ROUTE, methods: ['GET'])]
    public function __invoke(Request $request): Response
    {
        $session = $this->account->granted($request);
        if ($session instanceof Response) {
            return $session;
        }
        $archive = $this->handout->givenTo($session->user, $this->contexts->fromRequest($request));
        if ($archive === null || $archive->content === null) {
            throw new NotFoundHttpException('No archive is ready for this person.');
        }

        return $this->pages->download($archive->content, PersonalArchive::MEDIA_TYPE, $archive->fileName());
    }
}
