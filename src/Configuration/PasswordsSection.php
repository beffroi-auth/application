<?php

declare(strict_types=1);

namespace App\Configuration;

use App\Http\Timeout;
use App\Security\PwnedPasswords;
use Symfony\Component\Config\Definition\Builder\NodeBuilder;
use Symfony\Component\DependencyInjection\Loader\Configurator\ServicesConfigurator;

final readonly class PasswordsSection extends ConfigurationSection
{
    public const float DEFAULT_TIMEOUT = 2.0;

    public function name(): string
    {
        return 'passwords';
    }

    public function describe(NodeBuilder $children): void
    {
        $children
            ->arrayNode('passwords')
            ->info('What a password posed here is compared against before it is written (architecture §3.4): the list of compromised passwords, and nothing else; how long a password has to be and how hard to guess are rules of the core and not options.')
            ->addDefaultsIfNotSet()
            ->children()
            ->arrayNode('breached')
            ->info('The list of values known to be commonly used, expected or compromised a prospective password is compared against (NIST SP 800-63B §3.1.1.2).')
            ->addDefaultsIfNotSet()
            ->children()
            ->scalarNode('endpoint')
            ->info('Base URL of a range endpoint in the shape of Pwned Passwords ('
            . PwnedPasswords::RANGE_ENDPOINT
            . '), to which the first five hexadecimal characters of the SHA-1 of a password are appended; the comparison of the thirty-five that are left is local, and no API key is needed. Empty by default, and a deployment that leaves it empty asks nobody: it poses passwords and signs people in on the rules of the core alone, no password and no prefix of one ever leaving it.')
            ->defaultValue('')
            ->end()
            ->floatNode('timeout')
            ->info('How long a request to the list may take, in seconds, for the whole exchange. It bounds the console that writes a password for somebody and the page where a person replaces their own, where the answer is waited for; the lookup of a password already posed is a lane and waits on nothing.')
            ->defaultValue(self::DEFAULT_TIMEOUT)
            ->min(Timeout::MINIMUM)
            ->end()
            ->end()
            ->end()
            ->end()
            ->end();
    }

    public function load(array $config, ServicesConfigurator $services, bool $debug): void
    {
        $breached = self::section($this->own($config), 'breached');
        $services->get(PwnedPasswords::class)->arg('$endpoint', self::string($breached, 'endpoint'))->arg('$timeout', self::timeout(
            $breached,
            'timeout',
            'passwords.breached.timeout',
        ));
    }
}
