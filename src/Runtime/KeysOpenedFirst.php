<?php

declare(strict_types=1);

namespace App\Runtime;

use App\Key\KeysOpenedAtStart;
use LogicException;
use function sprintf;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\Runtime\RunnerInterface;
use Symfony\Contracts\Service\ResetInterface;

/**
 * Boots the kernel and opens the keys of the thread before the runner serves its first request.
 */
final readonly class KeysOpenedFirst implements RunnerInterface
{
    public function __construct(
        private KernelInterface $kernel,
        private RunnerInterface $runner,
    ) {
    }

    public function run(): int
    {
        $this->kernel->boot();
        $container = $this->kernel->getContainer();
        $keys = $container->get(KeysOpenedAtStart::BY_A_THREAD);
        if (! $keys instanceof KeysOpenedAtStart) {
            throw new LogicException(sprintf('The service "%s" is what a thread opens at start.', KeysOpenedAtStart::BY_A_THREAD));
        }
        $keys->open();
        // The kernel resets its services before each request but the first: the first one finds the keys open and
        // nothing else of the opening, as every later one does.
        $services = $container->get('services_resetter', ContainerInterface::NULL_ON_INVALID_REFERENCE);
        if ($services instanceof ResetInterface) {
            $services->reset();
        }

        return $this->runner->run();
    }
}
