<?php

declare(strict_types=1);

namespace App\Load;

use App\Doctrine\TenantProvider;
use App\Http\RequestContextFactory;
use App\Plane\Plane;
use Beffroi\Core\Domain\Event\LoadBudgetSpent;
use Beffroi\Core\Exception\LoadBudgetSpentException;
use Psr\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Throwable;

#[AsEventListener(event: KernelEvents::EXCEPTION, priority: self::PRIORITY)]
final readonly class LoadRefusalListener
{
    /**
     * Above the ErrorListener of the framework (-128), which renders what nothing else answered.
     */
    public const int PRIORITY = 0;

    public function __construct(
        private TenantProvider $tenants,
        private RequestContextFactory $contexts,
        private LoadRefusal $refusal,
        private EventDispatcherInterface $events,
    ) {
    }

    public function __invoke(ExceptionEvent $event): void
    {
        $spent = self::budgetBehind($event->getThrowable());
        if ($spent === null) {
            return;
        }
        $request = $event->getRequest();
        $route = $request->attributes->getString('_route');
        $context = $this->contexts->fromRequest($request);
        $this->events->dispatch(new LoadBudgetSpent($context->receivedAt, $this->tenants->provide(), $context, $spent->budget, $route, $spent->client));
        $event->setResponse($this->refusal->response(Plane::ofRoute($route), $spent->retryAfter, $request));
    }

    private static function budgetBehind(Throwable $throwable): ?LoadBudgetSpentException
    {
        for ($cause = $throwable; $cause !== null; $cause = $cause->getPrevious()) {
            if ($cause instanceof LoadBudgetSpentException) {
                return $cause;
            }
        }

        return null;
    }
}
