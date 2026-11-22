<?php

declare(strict_types=1);

namespace App\Controller;

use App\Http\Account\AsksForARecentProofInterface;
use App\Http\AccountAccess;
use App\Http\InteractionPages;
use App\Http\RequestContextFactory;
use App\Http\WhatWasRefused;
use App\Mail\MailKeyReaders;
use Beffroi\Core\Application\Identity\MailEncryptionChoice;
use Beffroi\Core\Domain\Identity\MailEncryptionRefusal;
use Beffroi\Core\Exception\MailEncryptionRefusedException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Takes the certificate or the key a person gives, in the format they chose, in place of the one they had given.
 */
#[AsController]
final readonly class AccountMailEncryptionGivingController implements AsksForARecentProofInterface
{
    public const string ROUTE = 'beffroi_interaction_account_mail_encryption_give';

    public const string CSRF_TOKEN_ID = 'beffroi_mail_encryption';

    public const string FORMAT_PARAMETER = 'format';

    public const string MATERIAL_PARAMETER = 'material';

    public function __construct(
        private AccountAccess $account,
        private MailKeyReaders $readers,
        private MailEncryptionChoice $choice,
        private RequestContextFactory $contexts,
        private WhatWasRefused $refusals,
        private InteractionPages $pages,
    ) {
    }

    public function comesBackTo(): string
    {
        return AccountMailEncryptionController::ROUTE;
    }

    #[Route('/account/mail-encryption', name: self::ROUTE, methods: ['POST'])]
    public function __invoke(Request $request): Response
    {
        $session = $this->account->granted($request, self::CSRF_TOKEN_ID);
        if ($session instanceof Response) {
            return $session;
        }
        $reader = $this->readers->of($request->request->getString(self::FORMAT_PARAMETER));
        try {
            if ($reader === null) {
                throw new MailEncryptionRefusedException(MailEncryptionRefusal::Unreadable, 'No format of this deployment has that name.');
            }
            $key = $reader->read($request->request->getString(self::MATERIAL_PARAMETER));
            $this->choice->choose($session->user, $key, $this->contexts->fromRequest($request));
        } catch (MailEncryptionRefusedException $refusal) {
            $this->refusals->keep($request, $refusal->refusal->value);
        }

        return $this->pages->afterTheForm(AccountMailEncryptionController::ROUTE);
    }
}
