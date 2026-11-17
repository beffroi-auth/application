<?php

declare(strict_types=1);

namespace App\Controller;

use Beffroi\Core\Exception\CredentialEnrolmentFailedException;
use App\Http\WhatWasRefused;
use App\Http\Account\AsksForARecentProofInterface;
use App\Http\AccountAccess;
use App\Http\InteractionPages;
use App\Http\RequestContextFactory;
use Beffroi\Core\Application\Totp\ActiveTotp;
use Beffroi\Core\Application\Totp\BackupCodesRenewal;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Answers with the page and never a redirection: codes shown after one would be codes written to a session.
 */
#[AsController]
final readonly class BackupCodesRenewalController implements AsksForARecentProofInterface
{
    public const string ROUTE = 'beffroi_interaction_account_two_factor_backup_codes';

    public const string CSRF_TOKEN_ID = 'beffroi_backup_codes_renew';

    public const string FEDERATED_DOMAIN = 'federated_domain';

    public function __construct(
        private AccountAccess $account,
        private ActiveTotp $totp,
        private BackupCodesRenewal $renewal,
        private RequestContextFactory $contexts,
        private InteractionPages $pages,
        private WhatWasRefused $refusals,
    ) {
    }

    public function comesBackTo(): string
    {
        return AccountTwoFactorController::ROUTE;
    }

    #[Route('/account/two-factor/backup-codes', name: self::ROUTE, methods: ['POST'])]
    public function __invoke(Request $request): Response
    {
        $session = $this->account->granted($request, self::CSRF_TOKEN_ID);
        if ($session instanceof Response) {
            return $session;
        }
        $totp = $this->totp->of($session->user);
        if ($totp === null) {
            throw new NotFoundHttpException('No second factor to renew the backup codes of.');
        }

        try {
            $codes = $this->renewal->renew($totp, $this->contexts->fromRequest($request));
        } catch (CredentialEnrolmentFailedException) {
            $this->refusals->keep($request, self::FEDERATED_DOMAIN);

            return $this->pages->afterTheForm(AccountTwoFactorController::ROUTE);
        }

        return $this->pages->render('account/backup_codes.html.twig', [
            'user' => $session->user,
            'backup_codes' => $codes,
        ]);
    }
}
