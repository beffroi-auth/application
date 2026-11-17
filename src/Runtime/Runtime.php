<?php

declare(strict_types=1);

namespace App\Runtime;

use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\Runtime\Runner\FrankenPhpWorkerRunner;
use Symfony\Component\Runtime\RunnerInterface;
use Symfony\Component\Runtime\SymfonyRuntime;

/**
 * The runtime of symfony/runtime, but a FrankenPHP worker thread opens its keys before its first request.
 */
final class Runtime extends SymfonyRuntime
{
    public function getRunner(?object $application): RunnerInterface
    {
        $runner = parent::getRunner($application);
        if ($runner instanceof FrankenPhpWorkerRunner && $application instanceof KernelInterface) {
            return new KeysOpenedFirst($application, $runner);
        }

        return $runner;
    }
}
