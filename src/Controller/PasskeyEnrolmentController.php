<?php

declare(strict_types=1);

namespace App\Controller;

use Beffroi\Core\Application\Identity\FederatedDomains;
use App\Http\Account\AsksForARecentProofInterface;
use App\Http\AccountAccess;
use App\Http\InteractionPages;
use App\Http\WhatWasRefused;
use App\Http\PasskeyOptionsStore;
use App\Http\RequestContextFactory;
use Beffroi\Core\Application\Passkey\PasskeyEnrolment;
use Beffroi\Core\Domain\Credential\PasskeyCredential;
use Beffroi\Core\Exception\CredentialEnrolmentFailedException;
use Beffroi\Core\Exception\PasskeyAttestationRefusedException;
use function mb_strlen;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;
use function trim;

#[AsController]
final readonly class PasskeyEnrolmentController implements AsksForARecentProofInterface
{
    public const string ROUTE = 'beffroi_interaction_account_passkeys_enrol';

    public const string CSRF_TOKEN_ID = 'beffroi_passkey_enrol';

    public const string NAME_PARAMETER = 'name';

    public const string ATTESTATION_PARAMETER = '_passkey';

    public const string INVALID_NAME = 'invalid_name';

    public const string NO_CEREMONY = 'no_ceremony';

    public const string REFUSED = 'refused';

    public const string FEDERATED_DOMAIN = 'federated_domain';

    public function __construct(
        private AccountAccess $account,
        private RequestContextFactory $contexts,
        private PasskeyEnrolment $enrolment,
        private PasskeyOptionsStore $options,
        private WhatWasRefused $refusals,
        private InteractionPages $pages,
        private FederatedDomains $federated,
    ) {
    }

    public function comesBackTo(): string
    {
        return AccountPasskeysController::ROUTE;
    }

    #[Route('/account/passkeys', name: self::ROUTE, methods: ['POST'])]
    public function __invoke(Request $request): Response
    {
        $session = $this->account->granted($request, self::CSRF_TOKEN_ID);
        if ($session instanceof Response) {
            return $session;
        }
        $user = $session->user;
        // The enrolment of the core refuses it too; this refusal says why.
        if (! $this->federated->admitsLocalCredentials($user)) {
            return $this->refused($request, self::FEDERATED_DOMAIN);
        }
        $name = trim($request->request->getString(self::NAME_PARAMETER));
        if ($name === '' || mb_strlen($name) > PasskeyCredential::NAME_MAX_LENGTH) {
            return $this->refused($request, self::INVALID_NAME);
        }
        $attestation = $request->request->getString(self::ATTESTATION_PARAMETER);
        $options = $this->options->takeCreation($request->getSession(), $user->id);
        if ($attestation === '' || $options === null) {
            return $this->refused($request, self::NO_CEREMONY);
        }
        try {
            $this->enrolment->complete($user, $name, $options, $attestation, $this->contexts->fromRequest($request));
        } catch (PasskeyAttestationRefusedException $exception) {
            return $this->refused($request, $exception->refusal->value);
        } catch (CredentialEnrolmentFailedException) {
            return $this->refused($request, self::REFUSED);
        }

        return $this->pages->afterTheForm(AccountPasskeysController::ROUTE);
    }

    private function refused(Request $request, string $code): Response
    {
        $this->refusals->keep($request, $code);

        return $this->pages->afterTheForm(AccountPasskeysController::ROUTE);
    }
}
