<?php

declare(strict_types=1);

namespace App\Http;

use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpKernel\Event\ResponseEvent;

/**
 * Takes the floor policy of the interaction plane off a page, so that nelmio/security-bundle writes the policy of
 * the deployment, which carries "frame-ancestors 'none'" too: two policies on one answer would both be enforced.
 * A page carrying a whole policy of its own is left alone; a redirection keeps the floor, nelmio writing none on it.
 */
#[AsEventListener(event: ResponseEvent::class, priority: self::BEFORE_THE_POLICY_IS_WRITTEN)]
final readonly class DeploymentPolicyPrevails
{
    /**
     * Above the listener of nelmio/security-bundle, which writes the policy at priority 0.
     */
    public const int BEFORE_THE_POLICY_IS_WRITTEN = 32;

    public const string HEADER = 'Content-Security-Policy';

    public function __invoke(ResponseEvent $event): void
    {
        if (! $event->isMainRequest()) {
            return;
        }

        $response = $event->getResponse();
        if ($response->isRedirection()) {
            return;
        }

        if ($response->headers->get(self::HEADER) === InteractionPages::HEADERS[self::HEADER]) {
            $response->headers->remove(self::HEADER);
        }
    }
}
