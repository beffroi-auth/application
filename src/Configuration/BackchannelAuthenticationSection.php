<?php

declare(strict_types=1);

namespace App\Configuration;

use App\Http\ClientNotificationSender;
use App\Http\Timeout;
use Beffroi\Oidc\Application\Ciba\UserHints;
use Symfony\Component\Config\Definition\Builder\NodeBuilder;
use Symfony\Component\DependencyInjection\Loader\Configurator\ServicesConfigurator;

final readonly class BackchannelAuthenticationSection extends ConfigurationSection
{
    public const float DEFAULT_NOTIFICATION_TIMEOUT = 3.0;

    public function name(): string
    {
        return 'backchannel_authentication';
    }

    public function describe(NodeBuilder $children): void
    {
        $children
            ->arrayNode('backchannel_authentication')
            ->info('The flow where a client asks for a user it names and the user approves on a device they already hold (CIBA Core §7). A client takes part in it by registering a grant and a delivery mode, one client at a time; nothing here turns it on for anybody.')
            ->addDefaultsIfNotSet()
            ->children()
            ->enumNode('login_hint')
            ->info('What a "login_hint" of a backchannel authentication request names for this tenant, read one way and never searched for (CIBA Core §7.1): "sub" the subject this provider issued to that client, "username" the name the user signs in with, "none" for a deployment that tags a reader of its own. CIBA Core §15 asks for an identifier that says nothing outside this provider, which "sub" is and a username is not. These are the names the login page reads its own hint by.')
            ->values(LoginHintReadings::names())
            ->defaultValue(LoginHintReadings::DEFAULT)
            ->end()
            ->floatNode('client_notification_timeout')
            ->info('Seconds one call to a client notification endpoint is given, connection included (CIBA Core §10.2). A client that hangs must not hold a worker; how many times the call is tried again is the retry policy of the notifications lane, as it is for a logout token.')
            ->defaultValue(self::DEFAULT_NOTIFICATION_TIMEOUT)
            ->min(Timeout::MINIMUM)
            ->end()
            ->end()
            ->end();
    }

    public function load(array $config, ServicesConfigurator $services, bool $debug): void
    {
        $backchannel = $this->own($config);
        $services->get(ClientNotificationSender::class)->arg('$timeout', self::timeout(
            $backchannel,
            'client_notification_timeout',
            'backchannel_authentication.client_notification_timeout',
        ));
        $services->get(UserHints::class)->args([LoginHintReadings::wired(
            'beffroi.oidc.ciba_user_hint',
            self::string($backchannel, 'login_hint'),
            'backchannel_authentication.login_hint',
        )]);
    }
}
