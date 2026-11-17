<?php

declare(strict_types=1);

namespace App\Controller;

use App\Http\CrossSitePost;
use App\Http\InteractionPages;
use App\Saml\ServiceProviderRoutes;
use App\Security\UpstreamAssertionAuthenticator;
use Beffroi\Saml\Application\Upstream\AssertionConsumerService;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;

/**
 * spec/saml.md S-52
 *
 * The firewall's UpstreamAssertionAuthenticator reads the response; this action answers the posts it declines.
 */
#[AsController]
final readonly class SamlAssertionConsumerServiceController
{
    public function __construct(
        private InteractionPages $pages,
        private CrossSitePost $crossSitePost,
        private ?AssertionConsumerService $consumer = null,
    ) {
    }

    #[Route(ServiceProviderRoutes::ASSERTION_CONSUMER_SERVICE_PATH, name: ServiceProviderRoutes::ASSERTION_CONSUMER_SERVICE, methods: ['POST'])]
    public function __invoke(Request $request): Response
    {
        if (! $this->consumer instanceof AssertionConsumerService) {
            throw new NotFoundHttpException('This tenant serves no SAML service provider.');
        }
        if (! CrossSitePost::isAForm($request)) {
            return $this->pages->error(
                Response::HTTP_UNSUPPORTED_MEDIA_TYPE,
                UpstreamAssertionAuthenticator::REFUSED,
                'A response posted to the assertion consumer service is a form, application/x-www-form-urlencoded (spec/saml.md S-52).',
            );
        }

        return (
            $this->crossSitePost->postAgain($request) ?? $this->pages->error(
                Response::HTTP_BAD_REQUEST,
                UpstreamAssertionAuthenticator::REFUSED,
                'The response answers no request this browser remembers (spec/saml.md S-53).',
            )
        );
    }
}
