<?php

declare(strict_types=1);

namespace App\Security;

use function array_find;
use App\Doctrine\TenantProvider;
use App\Http\RequestContextFactory;
use Beffroi\Core\Domain\Event\AuthenticationFailed;
use Psr\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\Security\Http\Event\LoginFailureEvent;

final readonly class LoginFailureListener
{
    /**
     * @param iterable<AnnouncesItsRefusalInterface> $logins
     */
    public function __construct(
        private TenantProvider $tenants,
        private RequestContextFactory $contexts,
        private EventDispatcherInterface $events,
        private iterable $logins,
    ) {
    }

    #[AsEventListener]
    public function onLoginFailure(LoginFailureEvent $event): void
    {
        $request = $event->getRequest();
        $route = $request->attributes->getString('_route');
        $login = array_find(
            [...$this->logins],
            static fn (AnnouncesItsRefusalInterface $candidate): bool => $candidate->route() === $route,
        );
        $failure = $login?->refusal($event->getException(), $event->getPassport());
        if ($failure === null) {
            return;
        }
        $context = $this->contexts->fromRequest($request);
        $this->events->dispatch(new AuthenticationFailed(
            $context->receivedAt,
            $this->tenants->provide(),
            $context,
            $failure->method,
            $failure->reason,
            $failure->identifier,
            $failure->user,
        ));
    }
}
