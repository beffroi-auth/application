<?php

declare(strict_types=1);

namespace App\Configuration;

use function array_keys;
use App\Routing\ProviderRoutes;
use App\Client\SelfClient;
use Beffroi\Core\Port\PasswordHasherInterface;
use Beffroi\Oidc\Application\Client\ClientMetadataReader;
use Beffroi\Oidc\Bridge\Configuration\ConfigurationClientRepository;
use function in_array;
use function is_array;
use function is_string;
use function preg_match;
use function sprintf;
use Symfony\Component\Config\Definition\Builder\ArrayNodeDefinition;
use Symfony\Component\Config\Definition\Builder\NodeBuilder;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\Config\Definition\NodeInterface;
use Symfony\Component\Config\Definition\Processor;
use Symfony\Component\DependencyInjection\Loader\Configurator\ServicesConfigurator;
use function Symfony\Component\DependencyInjection\Loader\Configurator\inline_service;
use function Symfony\Component\DependencyInjection\Loader\Configurator\service;

final readonly class SelfClientSection extends ConfigurationSection implements ExposesParametersInterface
{
    public const string SOURCE = 'beffroi.self_client';

    public const string ISSUER = 'beffroi.self_client.issuer';

    public const string CLIENT_ID = 'beffroi.self_client.client_id';

    /**
     * The environment variable the secret is read from, and never the secret.
     */
    public const string SECRET = 'beffroi.self_client.secret';

    /**
     * A "%env()%" placeholder and nothing else, its processors and name as EnvPlaceholderParameterBag accepts them.
     */
    private const string ENVIRONMENT_VARIABLE = '/^%env\((?:[-.\w\\\\]*+:)*+\w++\)%$/D';

    public function name(): string
    {
        return 'self_client';
    }

    public function describe(NodeBuilder $children): void
    {
        $children->append(self::declaration());
    }

    /**
     * A secret written in a file is refused here: the tree only ever sees what a variable stands for.
     */
    public function parameters(array $configs): array
    {
        $written = new Processor()->process(self::written(), $configs);
        $application = $written[$this->name()] ?? null;
        if (! is_array($application)) {
            return [];
        }
        $secret = self::string($application, 'secret');
        if (preg_match(self::ENVIRONMENT_VARIABLE, $secret) !== 1) {
            throw new InvalidConfigurationException(sprintf(
                'The "beffroi" configuration key "self_client.secret" is one environment variable, "%%env(NAME)%%": the secret of the application reaches its firewall as the parameter "%s", which names the variable and never holds the secret.',
                self::SECRET,
            ));
        }
        $parameters = [];
        $issuer = self::section($written, 'tenant')['issuer'] ?? null;
        if (is_string($issuer)) {
            $parameters[self::ISSUER] = $issuer;
        }
        $parameters[self::CLIENT_ID] = self::string($application, 'client_id');
        $parameters[self::SECRET] = $secret;

        return $parameters;
    }

    /**
     * @throws InvalidConfigurationException
     */
    public function load(array $config, ServicesConfigurator $services, bool $debug): void
    {
        $application = $config[$this->name()] ?? null;
        if (! is_array($application)) {
            return;
        }
        $clientId = self::string($application, 'client_id');
        $declared = [];
        foreach (array_keys(self::section($config, 'clients')) as $declaredId) {
            $declared[] = (string) $declaredId;
        }
        // An identifier from the environment is checked when the source is built.
        self::assertItsOwn($clientId, $declared);
        $tenant = self::section($config, 'tenant');
        $postLogoutRoute = $application['post_logout_route'] ?? null;
        $services
            ->set(self::SOURCE, ConfigurationClientRepository::class)
            ->factory(self::source(...))
            ->args([
                self::string($tenant, 'slug'),
                $clientId,
                $declared,
                inline_service(SelfClient::class)->args([
                    service(ProviderRoutes::URL_GENERATOR),
                    self::string($tenant, 'issuer'),
                    self::string($application, 'redirect_route'),
                    is_string($postLogoutRoute) ? $postLogoutRoute : null,
                    self::string($application, 'secret'),
                    '%kernel.secret%',
                ]),
                service(PasswordHasherInterface::class),
                service(ClientMetadataReader::class),
            ])
            ->tag('beffroi.oidc.client_source')
            ->tag('beffroi.oidc.client_inventory');
    }

    /**
     * @param list<string> $declared
     *
     * @throws InvalidConfigurationException
     */
    public static function source(
        string $tenantSlug,
        string $clientId,
        array $declared,
        SelfClient $application,
        PasswordHasherInterface $hasher,
        ClientMetadataReader $reader,
    ): ConfigurationClientRepository {
        self::assertItsOwn($clientId, $declared);

        return new ConfigurationClientRepository(
            $tenantSlug,
            [
                $clientId => $application->declaration(),
            ],
            $hasher,
            $reader,
        );
    }

    /**
     * The declared clients are asked first, so one under the identifier of the application would answer for it.
     *
     * @param list<string> $declared
     *
     * @throws InvalidConfigurationException
     */
    private static function assertItsOwn(string $clientId, array $declared): void
    {
        if (in_array($clientId, $declared, true)) {
            throw new InvalidConfigurationException(sprintf(
                'The "beffroi" configuration key "self_client.client_id" names "%s", a client "clients" declares as well: one identifier is one client of the tenant.',
                $clientId,
            ));
        }
    }

    private static function declaration(): ArrayNodeDefinition
    {
        $declaration = new ArrayNodeDefinition('self_client');
        $declaration
            ->info('The application, as a client of the provider it serves: it names its routes, and the provider registers the URLs the router writes for them in the context of the issuer, a route that requires neither a scheme nor a host being its path under the origin of the issuer. It asks for no consent, the named exception of a first-party application, and takes the defaults of a declared client for everything else. Absent, the application is no client of its provider. What its firewall needs of it is exposed as the parameters "beffroi.self_client.issuer", "beffroi.self_client.client_id" and "beffroi.self_client.secret".')
            ->children()
            ->scalarNode('client_id')
            ->info('The identifier the application is known by, which no client of "clients" may carry as well.')
            ->defaultValue('app')
            ->cannotBeEmpty()
            ->end()
            ->scalarNode('redirect_route')
            ->info('The route the provider sends the browser back to with a code, the callback of the firewall of the application ("_oidc_login_callback_<firewall>" for "oidc_login"): its URL is the one redirect URI the application registers (RFC 6749 §3.1.2).')
            ->isRequired()
            ->cannotBeEmpty()
            ->end()
            ->scalarNode('post_logout_route')
            ->info('The route the provider sends the browser back to after a logout the application asked for (RP-Initiated Logout §3); absent, it registers none.')
            ->defaultNull()
            ->end()
            ->scalarNode('secret')
            ->info('The secret the application authenticates with at the token endpoint, read from one environment variable and never written in a file: at least 32 visible ASCII characters, generated, never chosen, and never the secret of the kernel.')
            ->isRequired()
            ->cannotBeEmpty()
            ->end()
            ->end();

        return $declaration;
    }

    private static function written(): NodeInterface
    {
        $written = new ArrayNodeDefinition('beffroi');
        $written
            ->ignoreExtraKeys()
            ->children()
            ->arrayNode('tenant')
            ->ignoreExtraKeys()
            ->children()
            ->scalarNode('issuer')
            ->end()
            ->end()
            ->end()
            ->append(self::declaration())
            ->end();

        return $written->getNode(true);
    }
}
