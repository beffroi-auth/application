<?php

declare(strict_types=1);

namespace App\Controller;

use App\Http\Account\AsksForARecentProofInterface;
use App\Http\AccountAccess;
use App\Http\InteractionPages;
use App\Http\WhatWasRefused;
use App\Mail\MailKeyReaders;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Whether the mail of the provider is encrypted to the person, to what, and the form that gives or takes back the
 * certificate or the key, beside the address it is for. Reached with a recent proof only: what is given here decides
 * who can read what the provider tells the person.
 */
#[AsController]
final readonly class AccountMailEncryptionController implements AsksForARecentProofInterface
{
    public const string ROUTE = 'beffroi_interaction_account_mail_encryption';

    public function __construct(
        private AccountAccess $account,
        private MailKeyReaders $readers,
        private WhatWasRefused $refusals,
        private InteractionPages $pages,
    ) {
    }

    public function comesBackTo(): string
    {
        return self::ROUTE;
    }

    #[Route('/account/mail-encryption', name: self::ROUTE, methods: ['GET'])]
    public function __invoke(Request $request): Response
    {
        $session = $this->account->granted($request);
        if ($session instanceof Response) {
            return $session;
        }

        return $this->pages->render('account/mail_encryption.html.twig', [
            'user' => $session->user,
            'address' => $session->user->verifiedEmail(),
            'key' => $session->user->mailEncryption,
            'formats' => $this->readers->kinds(),
            'refusal' => $this->refusals->take($request)?->code,
        ]);
    }
}
