<?php

declare(strict_types=1);

namespace App\Administration\Controller;

use App\Administration\AdministrationAnswer;
use App\Administration\MailSigningKeyDocument;
use App\Mail\MailSignerInterface;
use Beffroi\Core\Port\MailSigningKeyRepositoryInterface;
use Beffroi\Oidc\Domain\Administration\ActingAdministrator;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;

/**
 * The keys the tenant signs its mail with, the current one of each format and the ones it replaced (decision 0038).
 */
#[AsController]
final readonly class MailSigningKeysController
{
    /**
     * @param iterable<MailSignerInterface> $signers
     */
    public function __construct(
        private MailSigningKeyRepositoryInterface $keys,
        private iterable $signers,
    ) {
    }

    #[Route('/tenant/mail-signing-keys', name: 'beffroi_administration_mail_signing_keys', methods: ['GET'])]
    public function __invoke(ActingAdministrator $administrator): Response
    {
        $keys = [];
        foreach ($this->signers as $signer) {
            foreach ($this->keys->ofKind($administrator->tenant, $signer->kind()) as $key) {
                $keys[] = MailSigningKeyDocument::of($key);
            }
        }

        return AdministrationAnswer::of(['keys' => $keys]);
    }
}
