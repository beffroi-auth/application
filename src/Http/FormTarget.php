<?php

declare(strict_types=1);

namespace App\Http;

use Beffroi\Oidc\Domain\Client\RedirectUri;
use LogicException;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\ResponseEvent;

/**
 * Adds to "form-action" the origins a form of the interaction plane may end at (CSP Level 3 §6.1, §6.3.2): a browser
 * checks that directive over the whole redirect chain of a submission, the redirection to a client included.
 */
final readonly class FormTarget
{
    private const string ATTRIBUTE = '_beffroi_form_targets';

    private const string DIRECTIVE = 'form-action';

    private PolicyCompletion $completion;

    public function __construct()
    {
        $this->completion = new PolicyCompletion(self::ATTRIBUTE, self::DIRECTIVE);
    }

    public function mayEndAt(Request $request, RedirectUri $uri): void
    {
        $this->mayReach($request, $uri->origin);
    }

    /**
     * @throws LogicException when the value is not an origin
     */
    public function mayReach(Request $request, string $origin): void
    {
        $this->completion->mayReach($request, $origin);
    }

    #[AsEventListener(event: ResponseEvent::class, priority: PolicyCompletion::AFTER_THE_POLICY_IS_WRITTEN)]
    public function onKernelResponse(ResponseEvent $event): void
    {
        $this->completion->complete($event);
    }
}
