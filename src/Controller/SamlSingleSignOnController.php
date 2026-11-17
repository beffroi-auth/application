<?php

declare(strict_types=1);

namespace App\Controller;

use App\Doctrine\TenantProvider;
use App\Http\CrossSitePost;
use App\Http\InteractionPages;
use App\Saml\IdentityProviderRoutes;
use App\Saml\SignOnAnswers;
use Beffroi\Saml\Application\SingleSignOn\SingleSignOn;
use Beffroi\Saml\Exception\SignOnRefusedException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;

/**
 * spec/saml.md section 6.4
 *
 * HTTP-Redirect is read from the raw QUERY_STRING, not the parsed query: the signature covers its octets (B-2).
 */
#[AsController]
final readonly class SamlSingleSignOnController
{
    public function __construct(
        private TenantProvider $tenants,
        private SingleSignOn $singleSignOn,
        private SignOnAnswers $answers,
        private InteractionPages $pages,
        private CrossSitePost $crossSitePost,
    ) {
    }

    #[Route(IdentityProviderRoutes::SINGLE_SIGN_ON_PATH, name: IdentityProviderRoutes::SINGLE_SIGN_ON, methods: ['GET', 'POST'])]
    public function __invoke(Request $request): Response
    {
        $tenant = $this->tenants->provide();
        try {
            if ($request->isMethod(Request::METHOD_POST)) {
                if (! CrossSitePost::isAForm($request)) {
                    return $this->pages->error(
                        Response::HTTP_UNSUPPORTED_MEDIA_TYPE,
                        SignOnAnswers::REFUSED,
                        'A request posted to the single sign-on service is a form, application/x-www-form-urlencoded (spec/saml.md section 6.8).',
                    );
                }
                $again = $this->crossSitePost->postAgain($request);
                if ($again !== null) {
                    return $again;
                }
                $signOn = $this->singleSignOn->receivePost($tenant, $request->request->all());
            } else {
                $signOn = $this->singleSignOn->receiveRedirect($tenant, $request->server->getString('QUERY_STRING'));
            }
        } catch (SignOnRefusedException $exception) {
            return $this->answers->refused($exception);
        }

        return $this->answers->answer($request, $tenant, $signOn);
    }
}
