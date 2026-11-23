<?php

declare(strict_types=1);

namespace App\Http;

use function array_key_exists;
use function array_pad;
use function explode;
use function implode;
use function is_array;
use function is_string;
use Psr\Log\LoggerInterface;
use Psr\Log\LoggerTrait;
use function rawurldecode;
use Stringable;
use function str_contains;
use Symfony\Contracts\Service\ResetInterface;

/**
 * The logger of the channel Symfony's router writes every matched request to, with its route parameters and its URI:
 * it keeps the path without the value a route carries, the value of a ticket or of a confirmation, and the names of
 * the query without their values. Production writes this channel out whenever the request fails
 * (config/packages/monolog.yaml), and the value of a ticket opens its act for whoever reads it (decision 0026).
 */
final readonly class RequestChannelLogger implements LoggerInterface, ResetInterface
{
    use LoggerTrait;

    public const string WITHHELD = 'REDACTED';

    public function __construct(
        private LoggerInterface $logger,
    ) {
    }

    /**
     * @param array<array-key, mixed> $context
     */
    public function log(mixed $level, string|Stringable $message, array $context = []): void
    {
        $this->logger->log($level, $message, self::withheld($context));
    }

    public function reset(): void
    {
        if ($this->logger instanceof ResetInterface) {
            $this->logger->reset();
        }
    }

    /**
     * @param array<array-key, mixed> $context
     *
     * @return array<array-key, mixed>
     */
    private static function withheld(array $context): array
    {
        $parameters = $context['route_parameters'] ?? null;
        $value = null;
        if (is_array($parameters) && array_key_exists(PresentedTicket::VALUE_PARAMETER, $parameters)) {
            $value = $parameters[PresentedTicket::VALUE_PARAMETER];
            $parameters[PresentedTicket::VALUE_PARAMETER] = self::WITHHELD;
            $context['route_parameters'] = $parameters;
        }
        $uri = $context['request_uri'] ?? null;
        if (is_string($uri)) {
            $context['request_uri'] = self::uri($uri, is_string($value) && $value !== '' ? $value : null);
        }

        return $context;
    }

    private static function uri(string $uri, ?string $value): string
    {
        [$path, $query] = array_pad(explode('?', $uri, 2), 2, null);
        if ($value !== null) {
            $segments = explode('/', (string) $path);
            foreach ($segments as $position => $segment) {
                if (rawurldecode($segment) === $value) {
                    $segments[$position] = self::WITHHELD;
                }
            }
            $path = implode('/', $segments);
        }
        if ($query === null) {
            return (string) $path;
        }
        $pairs = [];
        foreach (explode('&', $query) as $pair) {
            $pairs[] = str_contains($pair, '=') ? explode('=', $pair, 2)[0] . '=' . self::WITHHELD : $pair;
        }

        return $path . '?' . implode('&', $pairs);
    }
}
