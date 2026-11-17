<?php

declare(strict_types=1);

namespace App\Demonstration\Trace;

use function array_filter;
use function array_slice;
use function array_values;
use function hash;
use function is_array;
use function is_string;
use function preg_replace;
use Psr\Cache\CacheItemPoolInterface;
use Psr\Cache\InvalidArgumentException;
use Psr\Clock\ClockInterface;
use function sprintf;

final readonly class TraceStore
{
    public const int LIFETIME = 3600;

    /**
     * How many clients of one flow are remembered.
     */
    public const int CLIENTS = 12;

    public function __construct(
        private CacheItemPoolInterface $traces,
        private ClockInterface $clock,
    ) {
    }

    /**
     * @throws InvalidArgumentException
     */
    public function of(string $grantType, string $clientId): ?FlowTrace
    {
        $item = $this->traces->getItem(self::key($grantType, $clientId));
        $trace = $item->isHit() ? $item->get() : null;

        return $trace instanceof FlowTrace ? $trace : null;
    }

    /**
     * @throws InvalidArgumentException
     *
     * @return list<string> the one that played it last first
     */
    public function clientsOf(string $grantType): array
    {
        $item = $this->traces->getItem(self::clientsKey($grantType));
        $clients = $item->isHit() ? $item->get() : null;

        return is_array($clients) ? array_values(array_filter($clients, static fn (mixed $client): bool => is_string($client))) : [];
    }

    /**
     * @throws InvalidArgumentException
     */
    public function lastOf(string $grantType): ?FlowTrace
    {
        $clients = $this->clientsOf($grantType);

        return $clients === [] ? null : $this->of($grantType, $clients[0]);
    }

    /**
     * @throws InvalidArgumentException
     */
    public function opened(string $grantType, string $clientId): FlowTrace
    {
        return $this->save(new FlowTrace($grantType, $clientId, $this->clock->now()));
    }

    /**
     * @throws InvalidArgumentException
     */
    public function forget(string $grantType, string $clientId): void
    {
        $this->traces->deleteItem(self::key($grantType, $clientId));
    }

    /**
     * @throws InvalidArgumentException
     */
    public function save(FlowTrace $trace): FlowTrace
    {
        $this->keep(self::key($trace->grantType, $trace->clientId), $trace);
        $this->keep(
            self::clientsKey($trace->grantType),
            array_slice(
                [
                    $trace->clientId,
                    ...array_filter(
                        $this->clientsOf($trace->grantType),
                        static fn (string $client): bool => $client !== $trace->clientId,
                    ),
                ],
                0,
                self::CLIENTS,
            ),
        );

        return $trace;
    }

    /**
     * @throws InvalidArgumentException
     */
    private function keep(string $key, mixed $value): void
    {
        $item = $this->traces->getItem($key);
        $item->set($value);
        $item->expiresAfter(self::LIFETIME);
        $this->traces->save($item);
    }

    /**
     * A digest: a grant type is often an absolute URI, which carries characters PSR-6 reserves.
     */
    private static function key(string $grantType, string $clientId): string
    {
        return sprintf(
            'beffroi_demonstration.%s.%s',
            hash('xxh128', $grantType),
            preg_replace('/[^A-Za-z0-9_.-]/', '_', $clientId) ?? '',
        );
    }

    private static function clientsKey(string $grantType): string
    {
        return sprintf('beffroi_demonstration.clients.%s', hash('xxh128', $grantType));
    }
}
