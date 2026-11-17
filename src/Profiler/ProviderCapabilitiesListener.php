<?php

declare(strict_types=1);

namespace App\Profiler;

use function array_map;
use App\Doctrine\TenantNotSynchronizedException;
use App\Doctrine\TenantProvider;
use Beffroi\Oidc\Application\Discovery\DiscoveryHandler;
use function implode;
use function is_array;
use function is_bool;
use function is_scalar;
use LogicException;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;

#[AsEventListener(event: KernelEvents::RESPONSE, priority: self::PRIORITY)]
final readonly class ProviderCapabilitiesListener
{
    /**
     * Above the profiler, which collects at -100.
     */
    public const int PRIORITY = 0;

    public function __construct(
        private OpProfile $profile,
        private TenantProvider $tenants,
        private DiscoveryHandler $discovery,
    ) {
    }

    public function __invoke(ResponseEvent $event): void
    {
        if (! $event->isMainRequest() || ! $this->profile->hasTouchedTheProvider()) {
            return;
        }
        try {
            $metadata = $this->discovery->handle($this->tenants->provide())->toArray();
        } catch (LogicException|TenantNotSynchronizedException $exception) {
            $this->profile->record(new Observation('Provider', 'Provider', [
                'the document cannot be built' => $exception->getMessage(),
            ]));

            return;
        }
        $entries = [];
        foreach ($metadata as $member => $value) {
            $entries[$member] = self::written($value);
        }
        $this->profile->record(new Observation('Provider', 'What this provider serves', $entries));
    }

    private static function written(mixed $value): string
    {
        if (is_bool($value)) {
            return $value ? 'yes' : 'no';
        }
        if (is_array($value)) {
            return $value === [] ? 'none' : implode(' ', array_map(self::written(...), $value));
        }

        return is_scalar($value) ? (string) $value : 'a structure';
    }
}
