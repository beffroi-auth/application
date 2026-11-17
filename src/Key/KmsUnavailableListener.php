<?php

declare(strict_types=1);

namespace App\Key;

use App\Doctrine\TenantProvider;
use App\Http\RequestContextFactory;
use Beffroi\Core\Domain\Event\KmsUnavailable;
use Beffroi\Core\Exception\ExceptionTrail;
use Beffroi\Core\Exception\KmsUnavailableException;
use Psr\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Exception\ServiceUnavailableHttpException;
use Symfony\Component\HttpKernel\KernelEvents;
use Throwable;

/**
 * Turns an outage of the KMS into a 503 with a Retry-After, the same request being served once the KMS is back; the
 * audit is told nothing of what was being opened.
 */
final readonly class KmsUnavailableListener
{
    /**
     * Above the ErrorListener of the framework (-128), which renders what it is handed.
     */
    public const int PRIORITY = 0;

    public const int RETRY_AFTER = 10;

    public function __construct(
        private TenantProvider $tenants,
        private RequestContextFactory $contexts,
        private EventDispatcherInterface $events,
    ) {
    }

    #[AsEventListener(event: KernelEvents::EXCEPTION, priority: self::PRIORITY)]
    public function __invoke(ExceptionEvent $event): void
    {
        $outage = self::outageBehind($event->getThrowable());
        if ($outage === null) {
            return;
        }
        $context = $this->contexts->fromRequest($event->getRequest());
        $this->events->dispatch(new KmsUnavailable($context->receivedAt, $this->tenants->provide(), $context, ExceptionTrail::of($outage)));
        $event->setThrowable(
            new ServiceUnavailableHttpException(
                self::RETRY_AFTER,
                'The key management service did not answer or refused; the request is served once it is back.',
                $event->getThrowable(),
            ),
        );
    }

    public static function outageBehind(Throwable $throwable): ?KmsUnavailableException
    {
        for ($cause = $throwable; $cause !== null; $cause = $cause->getPrevious()) {
            if ($cause instanceof KmsUnavailableException) {
                return $cause;
            }
        }

        return null;
    }
}
