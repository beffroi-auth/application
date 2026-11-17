<?php

declare(strict_types=1);

namespace App\Controller;

use App\Doctrine\TenantProvider;
use App\Http\AuthorizationResponder;
use App\Http\InteractionPages;
use App\Http\LoginTarget;
use App\Saml\IdentityProviderRoutes;
use App\Saml\SignOnAnswers;
use App\Saml\WaitingSignOns;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;

/**
 * spec/saml.md S-18, section 6.4 step 3
 */
#[AsController]
final readonly class SamlSingleSignOnResumeController
{
    public function __construct(
        private TenantProvider $tenants,
        private WaitingSignOns $waiting,
        private LoginTarget $loginTargets,
        private SignOnAnswers $answers,
        private InteractionPages $pages,
    ) {
    }

    #[Route(IdentityProviderRoutes::SINGLE_SIGN_ON_RESUME_PATH, name: IdentityProviderRoutes::SINGLE_SIGN_ON_RESUME, methods: ['GET'])]
    public function __invoke(Request $request): Response
    {
        $tenant = $this->tenants->provide();
        $reference = $request->query->getString(IdentityProviderRoutes::REFERENCE_PARAMETER);
        $signOn = $reference === '' || ! $request->hasPreviousSession() ? null : $this->waiting->find($request->getSession(), $tenant, $reference);
        if ($signOn === null) {
            return $this->pages->error(Response::HTTP_BAD_REQUEST, AuthorizationResponder::UNKNOWN_INTERACTION);
        }
        $this->loginTargets->forgetIfItIsWaitingFor($request->getSession(), IdentityProviderRoutes::SINGLE_SIGN_ON_RESUME);

        return $this->answers->answer($request, $tenant, $signOn, $reference);
    }
}
