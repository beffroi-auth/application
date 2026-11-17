<?php

declare(strict_types=1);

namespace App\Controller;

use App\Doctrine\TenantProvider;
use App\Http\CrossSitePost;
use App\Http\InteractionPages;
use App\Http\RequestContextFactory;
use App\Saml\IdentityProviderRoutes;
use App\Saml\LogoutChainCookie;
use App\Saml\LogoutChainPages;
use Beffroi\Core\Exception\KeyNotFoundException;
use Beffroi\Saml\Application\SingleLogout\SingleLogout;
use Beffroi\Saml\Domain\SingleLogout\LogoutChainStarted;
use Beffroi\Saml\Exception\LogoutRefusedException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;

/**
 * spec/saml.md sections 6.5 and 6.6
 *
 * HTTP-Redirect is read from the raw QUERY_STRING, not the parsed query: the signature covers its octets (B-2).
 */
#[AsController]
final readonly class SamlSingleLogoutController
{
    public const string REFUSED = 'saml_logout_refused';

    public function __construct(
        private TenantProvider $tenants,
        private SingleLogout $singleLogout,
        private RequestContextFactory $contexts,
        private InteractionPages $pages,
        private LogoutChainPages $chain,
        private LogoutChainCookie $cookie,
        private CrossSitePost $crossSitePost,
    ) {
    }

    #[Route(IdentityProviderRoutes::SINGLE_LOGOUT_PATH, name: IdentityProviderRoutes::SINGLE_LOGOUT, methods: ['GET', 'POST'])]
    public function __invoke(Request $request): Response
    {
        $tenant = $this->tenants->provide();
        $context = $this->contexts->fromRequest($request);
        $secret = $this->cookie->presented($request);
        try {
            if ($request->isMethod(Request::METHOD_POST)) {
                if (! CrossSitePost::isAForm($request)) {
                    return $this->pages->error(
                        Response::HTTP_UNSUPPORTED_MEDIA_TYPE,
                        self::REFUSED,
                        'A request posted to the single logout service is a form, application/x-www-form-urlencoded (spec/saml.md section 6.8).',
                    );
                }
                $again = $this->crossSitePost->postAgain($request);
                if ($again !== null) {
                    return $again;
                }
                $answer = $this->singleLogout->post($tenant, $request->request->all(), $context, $secret);
            } else {
                $answer = $this->singleLogout->redirect($tenant, $request->server->getString('QUERY_STRING'), $context, $secret);
            }

            return $answer instanceof LogoutChainStarted ? $this->chain->started($tenant, $answer) : $this->chain->step($answer, $secret);
        } catch (LogoutRefusedException $exception) {
            return $this->pages->error(Response::HTTP_BAD_REQUEST, self::REFUSED, $exception->getMessage());
        } catch (KeyNotFoundException) {
            return $this->pages->error(Response::HTTP_NOT_FOUND, self::REFUSED, 'This tenant serves no SAML identity provider.');
        }
    }
}
