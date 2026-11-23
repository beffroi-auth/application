<?php

declare(strict_types=1);

namespace App\Http;

use function explode;
use function filter_var;
use const FILTER_FLAG_IPV6;
use const FILTER_VALIDATE_IP;
use function preg_match;
use function sprintf;
use function str_contains;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\HttpClient\NoPrivateNetworkHttpClient;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Decision 0006
 *
 * Refuses every private, loopback, link-local or reserved address but those of the networks the deployment names,
 * once the name is resolved and on every address the request connects to; the address checked is the one connected
 * to, so a name that resolves twice cannot rebind.
 */
final readonly class PrivateNetworkGuard implements OutboundGuardInterface
{
    /**
     * @param list<string> $reachable IP addresses and networks in CIDR notation
     *
     * @throws InvalidConfigurationException when one is neither
     */
    public function __construct(
        public array $reachable,
    ) {
        foreach ($reachable as $network) {
            if (! self::isANetwork($network)) {
                throw new InvalidConfigurationException(sprintf(
                    'The "beffroi" configuration key "outbound.reachable_private_networks" names "%s", which is neither an IP address nor a network in CIDR notation.',
                    $network,
                ));
            }
        }
    }

    public function guard(HttpClientInterface $client): HttpClientInterface
    {
        return new NoPrivateNetworkHttpClient($client, null, $this->reachable);
    }

    private static function isANetwork(string $network): bool
    {
        [$address, $bits] = str_contains($network, '/') ? explode('/', $network, 2) : [$network, null];
        if (filter_var($address, FILTER_VALIDATE_IP) === false) {
            return false;
        }
        if ($bits === null) {
            return true;
        }

        return preg_match('/^\d{1,3}$/D', $bits) === 1 && (int) $bits <= (filter_var($address, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6) === false ? 32 : 128);
    }
}
