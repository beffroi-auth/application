<?php

declare(strict_types=1);

namespace App\Administration\Controller;

use App\Administration\AdministrationAnswer;
use App\Administration\AskedDocument;
use App\Administration\MailSigningKeyDocument;
use App\Http\RequestContextFactory;
use App\Mail\MailSenderAddress;
use App\Mail\SmimeSigningKeys;
use Beffroi\Core\Application\Key\MailSigningKeyAdoption;
use Beffroi\Core\Exception\InvalidArgumentException;
use Beffroi\Oidc\Domain\Administration\ActingAdministrator;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Sets the S/MIME key the tenant signs its mail with, from a JSON object naming the private key, "private_key", and its
 * certificate followed by the chain above it, "certificates", both in PEM (decision 0038). The key is
 * checked against its certificate and sealed before it signs anything; the key it replaces signs nothing from then on.
 */
#[AsController]
final readonly class MailSmimeSigningKeyController
{
    private const string ASKED = 'An S/MIME key is set from a JSON object naming "private_key" and "certificates", both in PEM.';

    public function __construct(
        private SmimeSigningKeys $smime,
        private MailSigningKeyAdoption $adoption,
        private MailSenderAddress $sender,
        private RequestContextFactory $contexts,
    ) {
    }

    #[Route('/tenant/mail-signing-keys/smime', name: 'beffroi_administration_mail_smime_signing_key', methods: ['PUT'])]
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
        $asked = AskedDocument::of($request, 'S/MIME key asked for');
        try {
            $privateKey = $asked?->nullableString('private_key');
            $certificates = $asked?->nullableString('certificates');
            if ($privateKey === null || $certificates === null) {
                return AdministrationAnswer::refusal(Response::HTTP_BAD_REQUEST, 'invalid_request', self::ASKED);
            }
            $key = $this->smime->of($administrator->tenant, $privateKey, $certificates, $sender);
        } catch (InvalidArgumentException $refusal) {
            return AdministrationAnswer::refusal(Response::HTTP_BAD_REQUEST, 'invalid_request', $refusal->getMessage());
        }
        $this->adoption->adoptByAnAdministrator($key, $administrator->user->subject, $this->contexts->fromRequest($request));

        return AdministrationAnswer::of(MailSigningKeyDocument::of($key));
    }
}
