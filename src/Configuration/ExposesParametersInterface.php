<?php

declare(strict_types=1);

namespace App\Configuration;

use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;

/**
 * "debug:container --parameters" prints every parameter, so a secret is exposed as the environment variable it is
 * read from, never as its value.
 */
interface ExposesParametersInterface
{
    /**
     * @param list<mixed> $configs the "beffroi" blocks as the files wrote them, in file order
     *
     * @throws InvalidConfigurationException
     *
     * @return array<string, string> keyed by parameter name
     */
    public function parameters(array $configs): array;
}
