<?php

declare(strict_types=1);

namespace App\Configuration;

use Beffroi\Oidc\Application\Hint\LoginHints;
use Symfony\Component\Config\Definition\Builder\NodeBuilder;
use Symfony\Component\DependencyInjection\Loader\Configurator\ServicesConfigurator;

final readonly class InteractionSection extends ConfigurationSection
{
    public function name(): string
    {
        return 'interaction';
    }

    public function describe(NodeBuilder $children): void
    {
        $children
            ->arrayNode('interaction')
            ->info('The pages a browser is sent to while an authorization request waits for a login or a consent (architecture §4.3).')
            ->addDefaultsIfNotSet()
            ->children()
            ->enumNode('login_hint')
            ->info('What a "login_hint" of an authorization request names for this tenant, read one way and never searched for (OIDC Core §3.1.2.1): "sub" the subject this provider issued to that client, "username" the name the user signs in with, "none" for a deployment that tags a reader of its own. Read as a subject, the login page offers the name of the user it names, when the client may be told of that user, and nothing at all otherwise, the value itself being one the person never typed; read as a username, it offers the value as the client sent it, whoever it names, so that the page never tells whether an account holds it. These are the names the backchannel flow reads its own hint by.')
            ->values(LoginHintReadings::names())
            ->defaultValue(LoginHintReadings::DEFAULT)
            ->end()
            ->end()
            ->end();
    }

    public function load(array $config, ServicesConfigurator $services, bool $debug): void
    {
        $services->get(LoginHints::class)->args([LoginHintReadings::wired(
            'beffroi.oidc.login_hint',
            self::string($this->own($config), 'login_hint'),
            'interaction.login_hint',
        )]);
    }
}
