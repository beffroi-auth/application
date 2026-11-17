<?php

declare(strict_types=1);

namespace App\Saml;

use App\Http\BrowserSession;
use App\Http\InteractionPages;
use App\Http\RequestContextFactory;
use App\Http\LoginTarget;
use Beffroi\Core\Domain\Tenant\Tenant;
use Beffroi\Core\Exception\KeyNotFoundException;
use Beffroi\Saml\Application\SingleSignOn\SingleSignOn;
use Beffroi\Saml\Domain\SingleSignOn\SignOnRequest;
use Beffroi\Saml\Exception\SignOnRefusedException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * beffroi/saml, spec/saml.md section 6.4
 */
final readonly class SignOnAnswers
{
    public const string REFUSED = 'saml_request_refused';

    public function __construct(
        private SingleSignOn $singleSignOn,
        private BrowserSession $browser,
        private WaitingSignOns $waiting,
        private LoginTarget $loginTargets,
        private InteractionPages $pages,
        private SamlPostPage $page,
        private RequestContextFactory $contexts,
    ) {
    }

    /**
     * @param string|null $reference null when the request has just been received
     */
    public function answer(Request $request, Tenant $tenant, SignOnRequest $signOn, ?string $reference = null): Response
    {
        try {
            $form = $this->singleSignOn->answer(
                $tenant,
                $signOn,
                $this->browser->authenticationSession($request, $tenant),
                $this->contexts->fromRequest($request),
            );
        } catch (SignOnRefusedException $exception) {
            if ($reference !== null) {
                $this->waiting->forget($request->getSession(), $reference);
            }

            return $this->refused($exception);
        } catch (KeyNotFoundException) {
            return $this->pages->error(Response::HTTP_NOT_FOUND, self::REFUSED, 'This tenant serves no SAML identity provider.');
        }
        if ($form === null) {
            $httpSession = $request->getSession();

            return $this->loginTargets->sendToLogin($httpSession, IdentityProviderRoutes::SINGLE_SIGN_ON_RESUME, [
                IdentityProviderRoutes::REFERENCE_PARAMETER => $reference ?? $this->waiting->keep($httpSession, $tenant, $signOn),
            ]);
        }
        if ($reference !== null) {
            $this->waiting->forget($request->getSession(), $reference);
        }

        return $this->page->signOnResponse($form);
    }

    /**
     * The message is shown: it names a rule and never a value of the message (M-16, B-11).
     */
    public function refused(SignOnRefusedException $exception): Response
    {
        return $this->pages->error(Response::HTTP_BAD_REQUEST, self::REFUSED, $exception->getMessage());
    }
}
