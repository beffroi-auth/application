<?php

declare(strict_types=1);

namespace App\Administration\Controller;

use App\Administration\AdministrationAnswer;
use App\Administration\MailSigningKeyDocument;
use App\Http\RequestContextFactory;
use App\Mail\MailSenderAddress;
use App\Mail\PgpSigningKeys;
use Beffroi\Core\Application\Key\MailSigningKeyAdoption;
use Beffroi\Oidc\Domain\Administration\ActingAdministrator;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Draws a new OpenPGP key the tenant signs its mail with (RFC 3156 §5, decision 0038), sealed at once: the key it
 * replaces signs nothing from then on. Nothing is asked of the administrator, and no key is taken from them.
 */
#[AsController]
final readonly class MailPgpSigningKeyController
{
    public function __construct(
        private PgpSigningKeys $pgp,
        private MailSigningKeyAdoption $adoption,
        private MailSenderAddress $sender,
        private RequestContextFactory $contexts,
    ) {
    }

    #[Route('/tenant/mail-signing-keys/pgp', name: 'beffroi_administration_mail_pgp_signing_key', methods: ['POST'])]
    public function __invoke(ActingAdministrator $administrator, Request $request): Response
    {
        $sender = $this->sender->address();
        if ($sender === null) {
            return AdministrationAnswer::refusal(
                Response::HTTP_CONFLICT,
                'conflict',
                'This deployment writes no mail: it names no sender a key could sign for.',
            );
        }
        $key = $this->pgp->draw($administrator->tenant, $sender);
        $this->adoption->adoptByAnAdministrator($key, $administrator->user->subject, $this->contexts->fromRequest($request));

        return AdministrationAnswer::of(MailSigningKeyDocument::of($key), Response::HTTP_CREATED);
    }
}
