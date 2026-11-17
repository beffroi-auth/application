<?php

declare(strict_types=1);

namespace App\Configuration;

use Beffroi\Oidc\Exception\InvalidArgumentException;
use Beffroi\Oidc\Exception\LogicException;
use App\Client\SectorIdentifierDocumentFetcher;
use App\Http\Timeout;
use Beffroi\Oidc\Application\Client\Registration\SoftwareStatementVerifier;
use Beffroi\Oidc\Bridge\Configuration\ConfigurationClientRegistrationPolicy;
use Beffroi\Core\Port\SignatureAlgorithmsInterface;
use DateInterval;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use function is_string;
use Symfony\Component\Config\Definition\Builder\NodeBuilder;
use Symfony\Component\DependencyInjection\Loader\Configurator\ServicesConfigurator;

final readonly class ClientRegistrationSection extends ConfigurationSection
{
    public const int DEFAULT_KEEP_A_CLIENT_UNUSED_FOR = 7_776_000;

    public const int DEFAULT_KEEP_A_CLIENT_NEVER_USED_FOR = 604_800;

    public const float DEFAULT_SECTOR_IDENTIFIER_TIMEOUT = 3.0;

    public const int DEFAULT_SECTOR_IDENTIFIER_MAX_SIZE = 65536;

    public const int DEFAULT_STATEMENT_MAX_LIFETIME = 300;

    public const int DEFAULT_STATEMENT_LEEWAY = 30;

    public function name(): string
    {
        return 'client_registration';
    }

    public function describe(NodeBuilder $children): void
    {
        $children
            ->arrayNode('client_registration')
            ->info('How a client that nobody declared comes to exist here (RFC 7591, decision 0009). Absent, the tenant registers nobody, which is the default and what a deployment that writes nothing gets.')
            ->addDefaultsIfNotSet()
            ->children()
            ->scalarNode('mode')
            ->info('What authorises a registration, by the name the class that is that mode gives itself. This provider is wired with "initial_access_token", a credential the tenant issues, names, limits in number and duration and revokes (RFC 7591 section 1.2), with "open", which registers whoever asks with no credential at all (RFC 7591 section 1.3) and is written by a tenant that has no real user, and with "software_statement", a statement signed by an authority this tenant pins under "statement_issuers" below (RFC 7591 section 2.3), which is the mode that crosses the boundary of an organisation since the provider issues nothing. Left out, the tenant has no registration endpoint at all: it is not a mode of refusing, it is the absence of the endpoint. It is named per tenant and nothing here opens a mode for more than the tenant that names it.')
            ->defaultNull()
            ->end()
            ->integerNode('keep_a_client_unused_for')
            ->info('Seconds a client that was used may go without being used again before it is deleted (decision 0009). Ninety days by default, which is the absolute lifetime of a refresh token family this provider is configured with by default: past it, nothing that client was ever issued can still be presented, so deleting it takes nothing away from anybody.')
            ->defaultValue(self::DEFAULT_KEEP_A_CLIENT_UNUSED_FOR)
            ->min(1)
            ->end()
            ->integerNode('keep_a_client_never_used_for')
            ->info('Seconds a client that was never used lives at all (decision 0009). Seven days by default, a working week, so that a registration made on a Friday afternoon is still there the next Friday; a client that was never used holds nothing, so the only question is how long whoever registered it may take to make its first request. The mode that registers strangers makes it the shorter of the two.')
            ->defaultValue(self::DEFAULT_KEEP_A_CLIENT_NEVER_USED_FOR)
            ->min(1)
            ->end()
            ->floatNode('sector_identifier_timeout')
            ->info('Seconds one read of a sector_identifier_uri is given, connection included (OIDC Registration section 5). It is read once, when a client registers or replaces itself, and the registration waits for it.')
            ->defaultValue(self::DEFAULT_SECTOR_IDENTIFIER_TIMEOUT)
            ->min(Timeout::MINIMUM)
            ->end()
            ->integerNode('sector_identifier_max_size')
            ->info('Bytes a sector_identifier_uri may answer before the read stops; the document is the JSON array of the redirection URIs of the clients of one sector.')
            ->defaultValue(self::DEFAULT_SECTOR_IDENTIFIER_MAX_SIZE)
            ->min(1)
            ->end()
            ->append(self::statementIssuers())
            ->integerNode('statement_max_lifetime')
            ->info('Seconds ahead of the request the "exp" of a software statement may be (RFC 7591 section 2.3). A statement is signed for one registration, so it is not a credential for a year; RFC 7591 requires no "exp" at all, and this provider requires one as it does of every signed thing it takes back (decision 0006).')
            ->defaultValue(self::DEFAULT_STATEMENT_MAX_LIFETIME)
            ->min(1)
            ->end()
            ->integerNode('statement_leeway')
            ->info('Seconds allowed of the clock of the authority, both ways, when its statement is judged.')
            ->defaultValue(self::DEFAULT_STATEMENT_LEEWAY)
            ->min(0)
            ->end()
            ->scalarNode('default_scope')
            ->info('What a client that registers itself and names no scope is registered with (RFC 7591 section 2: "If omitted, an authorization server MAY register a client with a default set of scopes"), space-delimited as a request writes one (RFC 6749 section 3.3). Left out, such a client is registered with "openid" alone, which is less than this tenant hands out and is a default nobody chose. It is held to what the tenant announces like every registered scope, so a scope written here that the tenant does not offer refuses every registration rather than granting one, and it is read at the boot that declares it. A client that names its own scopes keeps them.')
            ->defaultNull()
            ->end()
            ->booleanNode('exempt_confidential_clients_from_pkce')
            ->info('The named exception of decision 0017, for the tenant that writes it and no other: the confidential clients this policy registers are registered without the PKCE requirement, because the Dynamic OP profile of OIDC Core sends no code_challenge and offers no way to. A public client is never exempted whatever this says, "plain" never exists, and a code_challenge a client does send is verified like any other. Write it with the reason a tenant has for it beside it; a tenant with real users has none.')
            ->defaultFalse()
            ->end()
            ->end()
            ->end();
    }

    /**
     * @throws InvalidConfigurationException when the list of the authorities is one the tree refuses
     * @throws InvalidArgumentException when an authority or the default set of scopes does not hold together
     * @throws LogicException when authorities are pinned and nothing verifies what they sign with
     */
    public static function policy(
        string $tenantSlug,
        ?string $mode,
        bool $exemptsConfidentialClientsFromPkce,
        DateInterval $keepsAClientUnusedFor,
        DateInterval $keepsAClientNeverUsedFor,
        mixed $statementIssuers,
        ?SignatureAlgorithmsInterface $algorithms = null,
        ?string $defaultScope = null,
    ): ConfigurationClientRegistrationPolicy {
        /** @var array<string, array<string, mixed>> $issuers keyed by "iss" */
        $issuers = self::statementIssuers()->normalized($statementIssuers, 'client_registration.statement_issuers');

        return new ConfigurationClientRegistrationPolicy(
            $tenantSlug,
            $mode,
            $exemptsConfidentialClientsFromPkce,
            $keepsAClientUnusedFor,
            $keepsAClientNeverUsedFor,
            $issuers,
            $algorithms,
            $defaultScope,
        );
    }

    private static function statementIssuers(): EnvironmentListDefinition
    {
        $issuers = new EnvironmentListDefinition('statement_issuers');
        $issuers
            ->info('The authorities whose software statements this tenant accepts (RFC 7591 section 2.3), keyed by the "iss" they sign with. They are what the "software_statement" mode reads, and a tenant that pins none has that mode closed rather than open to whoever signs. Each one is a closed declaration: nothing about it is inferred, and no key a statement carries is ever read.')
            ->useAttributeAsKey('issuer')
            ->normalizeKeys(false)
            ->arrayPrototype()
            ->children()
            ->variableNode('jwks')
            ->info('The JWK Set of the authority, inline (RFC 7517 section 5): public keys only, and the only keys a statement of this authority is ever verified against, whatever its header names (RFC 8725 section 3.10).')
            ->defaultNull()
            ->end()
            ->arrayNode('signing_algorithms')
            ->info('The JWS algorithms a statement of this authority may be signed with (RFC 7515 section 4.1.1), among those the provider is wired with; at least one. A name nothing verifies fails at boot.')
            ->scalarPrototype()
            ->end()
            ->end()
            ->end()
            ->end();

        return $issuers;
    }

    public function load(array $config, ServicesConfigurator $services, bool $debug): void
    {
        $registration = $this->own($config);
        $mode = $registration['mode'] ?? null;
        $defaultScope = $registration['default_scope'] ?? null;
        $services
            ->get(ConfigurationClientRegistrationPolicy::class)
            ->factory(self::policy(...))
            ->arg('$tenantSlug', self::string(self::section($config, 'tenant'), 'slug'))
            ->arg(
                '$mode',
                is_string($mode) ? $mode : null,
            )
            ->arg('$exemptsConfidentialClientsFromPkce', self::boolean($registration, 'exempt_confidential_clients_from_pkce'))
            ->arg('$defaultScope', is_string($defaultScope) ? $defaultScope : null)
            ->arg('$keepsAClientUnusedFor', self::bounded($registration, 'keep_a_client_unused_for', named: 'client_registration.keep_a_client_unused_for'))
            ->arg('$keepsAClientNeverUsedFor', self::bounded(
                $registration,
                'keep_a_client_never_used_for',
                named: 'client_registration.keep_a_client_never_used_for',
            ))
            ->arg('$statementIssuers', $registration['statement_issuers'] ?? []);
        $services->get(SoftwareStatementVerifier::class)->arg('$maximumLifetime', self::bounded(
            $registration,
            'statement_max_lifetime',
            named: 'client_registration.statement_max_lifetime',
        ))->arg('$leeway', self::bounded($registration, 'statement_leeway', named: 'client_registration.statement_leeway', minimum: 0));
        $services->get(SectorIdentifierDocumentFetcher::class)->arg('$timeout', self::timeout(
            $registration,
            'sector_identifier_timeout',
            'client_registration.sector_identifier_timeout',
        ))->arg('$maximumSize', self::bytes($registration, 'sector_identifier_max_size', 'client_registration.sector_identifier_max_size'));
    }
}
