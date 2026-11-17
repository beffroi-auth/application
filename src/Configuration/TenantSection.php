<?php

declare(strict_types=1);

namespace App\Configuration;

use function array_values;
use App\Identity\WiredClaims;
use App\Tenant\TenantFactory;
use Beffroi\Core\Application\Passkey\PasskeyAttestations;
use Beffroi\Core\Bridge\Configuration\ConfigurationAdministratorGroups;
use Beffroi\Core\Exception\InvalidArgumentException as CoreInvalidArgumentException;
use Beffroi\Core\Port\AuthenticationPredicatesInterface;
use Beffroi\Oidc\Bridge\Configuration\ConfigurationAdministrationResource;
use Beffroi\Oidc\Bridge\Configuration\ConfigurationDeclaredScopes;
use Beffroi\Oidc\Exception\InvalidArgumentException;
use Psr\Clock\ClockInterface;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use function is_string;
use Symfony\Component\Config\Definition\Builder\NodeBuilder;
use Symfony\Component\DependencyInjection\Loader\Configurator\ServicesConfigurator;

final readonly class TenantSection extends ConfigurationSection
{
    public function name(): string
    {
        return 'tenant';
    }

    public function describe(NodeBuilder $children): void
    {
        $children
            ->arrayNode('tenant')
            ->info('The one tenant of this deployment: the key is already a tenant, a table comes later.')
            ->isRequired()
            ->children()
            ->scalarNode('issuer')
            ->info('Issuer identifier (OIDC Discovery §3): an absolute HTTPS URL, no query, no fragment, no trailing slash; plain HTTP on a loopback host only.')
            ->isRequired()
            ->cannotBeEmpty()
            ->end()
            ->scalarNode('slug')
            ->info('Stable name of the tenant, from which its identifier is derived; renaming it is creating another tenant.')
            ->defaultValue('default')
            ->cannotBeEmpty()
            ->end()
            ->scalarNode('name')
            ->info('Display name; the host of the issuer when absent.')
            ->defaultNull()
            ->end()
            ->arrayNode('look')
            ->info('What this tenant shows of itself on every page of its provider besides its name (issue #449). A deployment that declares none of it is served the pages it has always been served.')
            ->addDefaultsIfNotSet()
            ->children()
            ->scalarNode('logo_uri')
            ->info('Where the logo of the tenant is served from: an absolute HTTPS URL, no fragment, no credentials; plain HTTP on a loopback host only. The browser of the visitor loads it, never the provider, and the pages name its origin in their Content-Security-Policy.')
            ->defaultNull()
            ->end()
            ->scalarNode('primary_color')
            ->info('The colour the buttons and the links of the pages are drawn in: three or six hexadecimal digits behind a hash, as "#1b4b8f". It needs "primary_content_color", a colour being unusable without the colour of what is written on it.')
            ->defaultNull()
            ->end()
            ->scalarNode('primary_content_color')
            ->info('The colour of what is written on "primary_color", the two being declared together or not at all.')
            ->defaultNull()
            ->end()
            ->end()
            ->end()
            ->append(self::selfServedClaims())
            ->append(self::authenticationClasses())
            ->append(self::scopes())
            ->append(self::administrators())
            ->scalarNode('administration_resource')
            ->info('The identifier of the administration API of this tenant (decision 0038), the resource its tokens are addressed to (RFC 8707 section 2): an absolute URI without a fragment, the URL that API is served at. The provider reserves that audience to the administrators of the tenant: it addresses a token to it only for a member of one of the groups "administrators" names, signed in at the authorization endpoint by a session whose proofs satisfy phishing_resistant whatever the floor of the tenant, and bound to a key of the client; the device authorization grant and the backchannel authentication grant are refused it, and the discovery document never announces it. A client still registers it among its resources to ask for it. None by default, which reserves nothing.')
            ->defaultNull()
            ->end()
            ->scalarNode('passkey_attestation')
            ->info('What the enrolment of a passkey asks of its authenticator (WebAuthn Level 3 §5.4.7, decision 0046), by the name a policy wired in the provider gives itself: "none", the model being the one the authenticator declares; "direct", the model verified when the FIDO metadata know it and declared otherwise, a passkey never being refused for want of an attestation; "enterprise", only an attestation that chains up to a root the tenant pins enrolling. Under "direct" and "enterprise" a model whose last status report is a security notification is refused, save those "passkey_compromised_models" names. A name no wired policy carries refuses the start, naming the ones that are wired. "none" by default.')
            ->defaultValue('none')
            ->cannotBeEmpty()
            ->end()
            ->append(self::roots(
                'passkey_attestation_roots',
                'The roots of the enterprise attestations the tenant admits, in PEM, under "enterprise" only: an attestation that chains up to one of them carries the serial number of its authenticator (WebAuthn Level 3 §8.2.2), which no attestation of a whole range of a maker carries. A certificate that is no certificate authority refuses the start, as "enterprise" pinning no root at all does. None by default.',
            ))
            ->append(self::roots(
                'passkey_attestation_own_roots',
                'The authorities of the tenant itself, in PEM, under "enterprise" only: the ones that sign the identities of its own devices, under which the attestation is the proof and no serial number is required. None by default.',
            ))
            ->append(self::compromisedModels())
            ->end()
            ->end();
    }

    /**
     * @param array<array-key, mixed> $look
     *
     * @throws InvalidConfigurationException
     */
    public static function tenantFactory(
        string $issuer,
        string $slug,
        ?string $name,
        ClockInterface $clock,
        AuthenticationPredicatesInterface $predicates,
        PasskeyAttestations $passkeyAttestations,
        mixed $authenticationClasses = [],
        array $look = [],
        mixed $selfServedClaims = [],
        ?WiredClaims $claims = null,
        string $passkeyAttestation = 'none',
        mixed $passkeyAttestationRoots = [],
        mixed $passkeyAttestationOwnRoots = [],
        mixed $passkeyCompromisedModels = [],
    ): TenantFactory {
        if ($slug === '') {
            throw new InvalidConfigurationException('The "beffroi" configuration key "tenant.slug" is the stable name of the tenant, and it is empty.');
        }

        return new TenantFactory(
            $issuer,
            $slug,
            $name,
            $clock,
            $predicates,
            self::authenticationClasses()->normalized($authenticationClasses, 'tenant.authentication_classes'),
            $look,
            self::selfServedClaims()->normalized($selfServedClaims, 'tenant.self_served_claims'),
            $claims,
            $passkeyAttestations,
            [
                'policy' => $passkeyAttestation,
                'roots' => self::roots('passkey_attestation_roots', '')->normalized($passkeyAttestationRoots, 'tenant.passkey_attestation_roots'),
                'own_roots' => self::roots('passkey_attestation_own_roots', '')
                    ->normalized($passkeyAttestationOwnRoots, 'tenant.passkey_attestation_own_roots'),
                'models_under_alert' => self::compromisedModels()->normalized($passkeyCompromisedModels, 'tenant.passkey_compromised_models'),
            ],
        );
    }

    /**
     * @throws InvalidConfigurationException
     * @throws InvalidArgumentException
     */
    public static function declaredScopes(string $tenantSlug, mixed $scopes): ConfigurationDeclaredScopes
    {
        /** @var list<string> $declared */
        $declared = array_values(self::scopes()->normalized($scopes, 'tenant.scopes'));

        return new ConfigurationDeclaredScopes($tenantSlug, $declared);
    }

    /**
     * @throws InvalidConfigurationException
     * @throws CoreInvalidArgumentException
     */
    public static function administratorGroups(string $tenantSlug, mixed $groups): ConfigurationAdministratorGroups
    {
        /** @var list<string> $named */
        $named = array_values(self::administrators()->normalized($groups, 'tenant.administrators'));

        return new ConfigurationAdministratorGroups($tenantSlug, $named);
    }

    private static function administrators(): EnvironmentListDefinition
    {
        $groups = new EnvironmentListDefinition('administrators');
        $groups
            ->info('The groups whose members administer this tenant (decision 0038), by their names in the tenant, exactly: the role of an administrator is read from the groups of the person, a local group the console fills ("beffroi:group:add-member") as well as a group an upstream provider or SCIM holds the members of (decision 0002), and never from a list of people. A person taken out of the group stops administering the tenant at once: the next token addressed to "administration_resource" is refused. A name that is empty, or that begins or ends with whitespace, refuses the start; a name no group carries yet is kept. None by default, so that nobody administers a tenant whose deployment did not say who does.')
            ->stringPrototype()
            ->end()
            ->defaultValue([]);

        return $groups;
    }

    private static function scopes(): EnvironmentListDefinition
    {
        $scopes = new EnvironmentListDefinition('scopes');
        $scopes
            ->info('The scopes this tenant hands out tokens for beyond "openid", the scopes of the claims this provider serves and "offline_access" (issue #920): the scopes of the APIs it protects, which stand for no claim. The tenant offers them, its discovery document announces them in "scopes_supported", and a client that registers itself may register them, a registration being held to what the tenant offers (decision 0009). A scope a declared client registers is offered as well, so a deployment that declares its clients may name none here; one that declares none, as the image, has nothing else to make such a scope exist. "offline_access" is never one of them, the wired grants deciding it (OIDC Core section 11): it is refused, as a value that is no scope token (RFC 6749 section 3.3) is, when the service that reads the list is built. None by default.')
            ->stringPrototype()
            ->end()
            ->defaultValue([]);

        return $scopes;
    }

    private static function selfServedClaims(): EnvironmentListDefinition
    {
        $claims = new EnvironmentListDefinition('self_served_claims');
        $claims
            ->info('The claims the people of this tenant may change of themselves on the page of their account (issue #452), by the names of OIDC Core §5.1. It is a rule of the tenant and never an option of the deployment: a tenant whose directory owns the data opens none, a tenant that creates its people by hand may open all of them, and one instance serves both. Nothing by default, because opening a claim is a decision somebody takes where closing one would be a decision nobody took. A name no claim of this provider answers refuses the start.')
            ->scalarPrototype()
            ->end()
            ->defaultValue([]);

        return $claims;
    }

    private static function roots(string $name, string $info): EnvironmentListDefinition
    {
        $roots = new EnvironmentListDefinition($name);
        $roots->info($info)->scalarPrototype()->cannotBeEmpty()->end()->defaultValue([]);

        return $roots;
    }

    private static function compromisedModels(): EnvironmentListDefinition
    {
        $models = new EnvironmentListDefinition('passkey_compromised_models');
        $models
            ->info('The models whose last status report in the FIDO metadata is a security notification (FIDO Metadata Service v3.1.1 §3.1.4.2) the tenant still enrols under "direct" and "enterprise", each the AAGUID of its authenticator to the last day it is admitted, as "2027-03-31" (decision 0006: an exception is named and bounded): until then a passkey of that model enrols and is marked under alert, after it the refusal returns. None by default.')
            ->useAttributeAsKey('aaguid')
            ->normalizeKeys(false)
            ->scalarPrototype()
            ->cannotBeEmpty()
            ->end()
            ->defaultValue([]);

        return $models;
    }

    private static function authenticationClasses(): EnvironmentListDefinition
    {
        $classes = new EnvironmentListDefinition('authentication_classes');
        $classes
            ->info('The "acr" vocabulary of the tenant (decision 0010): class names, in the order a session is answered in, each on a predicate of the provider, by the name that predicate gives itself. The default policy ("phrh", "phr", "2", "1") when nothing is named. The predicates are the ones the deployment is wired with and this tree keeps no list of them beside the wiring: a name none of them carries refuses the start, naming the ones that are wired.')
            ->useAttributeAsKey('name')
            ->normalizeKeys(false)
            ->scalarPrototype()
            ->cannotBeEmpty()
            ->end()
            ->defaultValue([]);

        return $classes;
    }

    public function load(array $config, ServicesConfigurator $services, bool $debug): void
    {
        $tenant = $this->own($config);
        $services
            ->get(TenantFactory::class)
            ->factory(self::tenantFactory(...))
            ->arg('$issuer', self::string($tenant, 'issuer'))
            ->arg('$slug', self::string($tenant, 'slug'))
            ->arg(
                '$name',
                is_string($tenant['name'] ?? null) ? $tenant['name'] : null,
            )
            ->arg('$authenticationClasses', $tenant['authentication_classes'] ?? [])
            ->arg('$look', self::section($tenant, 'look'))
            ->arg('$selfServedClaims', $tenant['self_served_claims'] ?? [])
            ->arg('$passkeyAttestation', self::string($tenant, 'passkey_attestation'))
            ->arg('$passkeyAttestationRoots', $tenant['passkey_attestation_roots'] ?? [])
            ->arg('$passkeyAttestationOwnRoots', $tenant['passkey_attestation_own_roots'] ?? [])
            ->arg('$passkeyCompromisedModels', $tenant['passkey_compromised_models'] ?? []);
        $services->get(ConfigurationDeclaredScopes::class)->factory(self::declaredScopes(...))->arg('$tenantSlug', self::string($tenant, 'slug'))->arg(
            '$scopes',
            $tenant['scopes'] ?? [],
        );
        $services->get(ConfigurationAdministratorGroups::class)->factory(self::administratorGroups(...))->arg('$tenantSlug', self::string(
            $tenant,
            'slug',
        ))->arg(
            '$groups',
            $tenant['administrators'] ?? [],
        );
        $services->get(ConfigurationAdministrationResource::class)->arg('$tenantSlug', self::string($tenant, 'slug'))->arg(
            '$resource',
            is_string($tenant['administration_resource'] ?? null) ? $tenant['administration_resource'] : null,
        );
    }
}
