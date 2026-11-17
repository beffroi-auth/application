<?php

declare(strict_types=1);

namespace App\Http;

use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\ResponseEvent;

/**
 * Adds to "img-src" the origins of the logos a page shows, a client's or its tenant's (CSP Level 3 §6.1).
 */
final readonly class ImageSource
{
    private const string ATTRIBUTE = '_beffroi_image_sources';

    private const string DIRECTIVE = 'img-src';

    private const string FALLBACK = 'default-src';

    private PolicyCompletion $completion;

    public function __construct()
    {
        $this->completion = new PolicyCompletion(self::ATTRIBUTE, self::DIRECTIVE, self::FALLBACK);
    }

    /**
     * @param list<string> $origins
     */
    public function mayLoadFrom(Request $request, array $origins): void
    {
        foreach ($origins as $origin) {
            $this->completion->mayReach($request, $origin);
        }
    }

    #[AsEventListener(event: ResponseEvent::class, priority: PolicyCompletion::AFTER_THE_POLICY_IS_WRITTEN)]
    public function onKernelResponse(ResponseEvent $event): void
    {
        $this->completion->complete($event);
    }
}
