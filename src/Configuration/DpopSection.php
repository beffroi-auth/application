<?php

declare(strict_types=1);

namespace App\Configuration;

use App\Dpop\CachedDpopNonce;
use App\Dpop\DpopNonceRequirement;
use Beffroi\Oidc\Application\Dpop\DpopProofValidator;
use Beffroi\Oidc\Bridge\Null\NoDpopNonce;
use Beffroi\Oidc\Port\DpopNonceInterface;
use Symfony\Component\Config\Definition\Builder\NodeBuilder;
use Symfony\Component\DependencyInjection\Loader\Configurator\ServicesConfigurator;
use function Symfony\Component\DependencyInjection\Loader\Configurator\service_closure;

final readonly class DpopSection extends ConfigurationSection
{
    public const int DEFAULT_DPOP_PROOF_MAX_AGE = 60;

    /**
     * The FAPI 2 conformance suite sends a proof dated ten seconds ahead and expects it accepted.
     */
    public const int DEFAULT_DPOP_LEEWAY = 30;

    public const int DEFAULT_DPOP_NONCE_TTL = 300;

    public function name(): string
    {
        return 'dpop';
    }

    public function describe(NodeBuilder $children): void
    {
        $children
            ->arrayNode('dpop')
            ->info('How a proof of possession is read (RFC 9449 §4.3). What a proof may be signed with is the wiring, the same algorithms the provider signs with, and what the document announces in "dpop_signing_alg_values_supported"; nothing here weakens a proof, and a client that sends none is not concerned.')
            ->addDefaultsIfNotSet()
            ->children()
            ->integerNode('proof_max_age')
            ->info('Seconds a proof stands, from its "iat", before the leeway is added (RFC 9449 §4.3). A proof is a signature over one request being sent; a long window is a window a proof taken off the wire may be sent again in.')
            ->defaultValue(self::DEFAULT_DPOP_PROOF_MAX_AGE)
            ->min(1)
            ->end()
            ->integerNode('leeway')
            ->info('Seconds allowed of the clock of a client, both ways: a proof is taken up to this many seconds ahead of the clock of the provider, and until "proof_max_age" plus this many seconds after its "iat".')
            ->defaultValue(self::DEFAULT_DPOP_LEEWAY)
            ->min(0)
            ->end()
            ->arrayNode('nonce')
            ->info('Whether this provider asks a proof to carry a nonce of its own (RFC 9449 §8), which is the server\'s to decide: the "iat" of a proof is what its client says, a nonce is what the provider knows. A client that speaks DPoP handles the refusal that hands it one; one that does not is not concerned either way.')
            ->addDefaultsIfNotSet()
            ->children()
            ->booleanNode('required')
            ->defaultFalse()
            ->end()
            ->integerNode('ttl')
            ->info('Seconds a nonce is accepted once issued.')
            ->defaultValue(self::DEFAULT_DPOP_NONCE_TTL)
            ->min(1)
            ->end()
            ->end()
            ->end()
            ->end()
            ->end();
    }

    public function load(array $config, ServicesConfigurator $services, bool $debug): void
    {
        $dpop = $this->own($config);
        $nonces = self::section($dpop, 'nonce');
        $services->get(DpopProofValidator::class)->arg('$maximumAge', self::bounded(
            $dpop,
            'proof_max_age',
            named: 'dpop.proof_max_age',
        ))->arg('$leeway', self::bounded($dpop, 'leeway', named: 'dpop.leeway', minimum: 0));
        $services->get(CachedDpopNonce::class)->arg('$lifetime', self::bounded($nonces, 'ttl', named: 'dpop.nonce.ttl'));
        $services
            ->set(DpopNonceInterface::class)
            ->factory(DpopNonceRequirement::chosen(...))
            ->args([
                self::boolean($nonces, 'required'),
                service_closure(CachedDpopNonce::class),
                service_closure(NoDpopNonce::class),
            ]);
    }
}
