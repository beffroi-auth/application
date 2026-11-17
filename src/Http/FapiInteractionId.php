<?php

declare(strict_types=1);

namespace App\Http;

use function count;
use Beffroi\Core\Port\UuidGeneratorInterface;
use function is_string;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\ControllerEvent;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Uid\Uuid;

/**
 * FAPI 1.0 Advanced Final §6.2.1
 */
final readonly class FapiInteractionId
{
    public const string HEADER = 'x-fapi-interaction-id';

    private const string ATTRIBUTE = '_beffroi_fapi_interaction_id';

    public function __construct(
        private UuidGeneratorInterface $uuids,
    ) {
    }

    #[AsEventListener(event: KernelEvents::CONTROLLER)]
    public function onKernelController(ControllerEvent $event): void
    {
        if (! $event->getController() instanceof ServesAProtectedResourceInterface) {
            return;
        }
        $request = $event->getRequest();
        $request->attributes->set(self::ATTRIBUTE, $this->ofRequest($request));
    }

    #[AsEventListener(event: KernelEvents::RESPONSE)]
    public function onKernelResponse(ResponseEvent $event): void
    {
        $interactionId = $event->getRequest()->attributes->get(self::ATTRIBUTE);
        if (! is_string($interactionId)) {
            return;
        }
        $event->getResponse()->headers->set(self::HEADER, $interactionId);
    }

    private function ofRequest(Request $request): string
    {
        $named = $request->headers->all(self::HEADER);
        $one = count($named) === 1 ? $named[0] : null;

        return $one !== null && Uuid::isValid($one) ? $one : $this->uuids->opaque()->toRfc4122();
    }
}
