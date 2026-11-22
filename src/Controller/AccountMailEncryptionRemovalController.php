<?php

declare(strict_types=1);

namespace App\Controller;

use App\Http\Account\AsksForARecentProofInterface;
use App\Http\AccountAccess;
use App\Http\InteractionPages;
use App\Http\RequestContextFactory;
use Beffroi\Core\Application\Identity\MailEncryptionChoice;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Takes back the certificate or the key a person gave: the mail of the provider is written to them in clear again.
 */
#[AsController]
final readonly class AccountMailEncryptionRemovalController implements AsksForARecentProofInterface
{
    public const string ROUTE = 'beffroi_interaction_account_mail_encryption_remove';

    public function __construct(
        private AccountAccess $account,
        private MailEncryptionChoice $choice,
        private RequestContextFactory $contexts,
        private InteractionPages $pages,
    ) {
    }

    public function comesBackTo(): string
    {
        return AccountMailEncryptionController::ROUTE;
    }

    #[Route('/account/mail-encryption/remove', name: self::ROUTE, methods: ['POST'])]
    public function __invoke(Request $request): Response
    {
        $session = $this->account->granted($request, AccountMailEncryptionGivingController::CSRF_TOKEN_ID);
        if ($session instanceof Response) {
            return $session;
        }
        $this->choice->choose($session->user, null, $this->contexts->fromRequest($request));

        return $this->pages->afterTheForm(AccountMailEncryptionController::ROUTE);
    }
}
