<?php

declare(strict_types=1);

namespace App\Configuration;

use Symfony\Component\Config\Definition\Builder\NodeBuilder;
use Symfony\Component\DependencyInjection\Loader\Configurator\ServicesConfigurator;

interface ConfigurationSectionInterface
{
    /**
     * @return non-empty-string
     */
    public function name(): string;

    public function describe(NodeBuilder $children): void;

    /**
     * @param array<array-key, mixed> $config the whole "beffroi" configuration, validated against the tree
     */
    public function load(array $config, ServicesConfigurator $services, bool $debug): void;
}
