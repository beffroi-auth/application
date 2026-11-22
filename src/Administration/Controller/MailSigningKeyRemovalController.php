<?php

declare(strict_types=1);

namespace App\Administration\Controller;

use App\Administration\AdministrationAnswer;
use App\Administration\MailSigningKeyDocument;
use App\Http\RequestContextFactory;
use Beffroi\Core\Application\Key\MailSigningKeyAdoption;
use Beffroi\Core\Port\MailSigningKeyRepositoryInterface;
use Beffroi\Oidc\Domain\Administration\ActingAdministrator;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Removes a key the tenant signs its mail with (decision 0038): a replaced key is served no more, and the current one
 * taken away leaves its format signing nothing.
 */
#[AsController]
final readonly class MailSigningKeyRemovalController
{
    public function __construct(
        private MailSigningKeyRepositoryInterface $keys,
        private MailSigningKeyAdoption $adoption,
        private RequestContextFactory $contexts,
    ) {
    }

    #[Route(
        '/tenant/mail-signing-keys/{format}/{fingerprint}',
        name: 'beffroi_administration_mail_signing_key_removal',
        requirements: ['format' => '[a-z]+', 'fingerprint' => '[0-9A-F]{40,64}'],
        methods: ['DELETE'],
    )]
    public function __invoke(ActingAdministrator $administrator, Request $request, string $format, string $fingerprint): Response
    {
        foreach ($this->keys->ofKind($administrator->tenant, $format) as $key) {
            if ($key->fingerprint === $fingerprint) {
                $this->adoption->removeByAnAdministrator($key, $administrator->user->subject, $this->contexts->fromRequest($request));

                return AdministrationAnswer::of(MailSigningKeyDocument::of($key));
            }
        }

        return AdministrationAnswer::refusal(Response::HTTP_NOT_FOUND, 'not_found', 'The tenant has no mail signing key of this format and fingerprint.');
    }
}
