<?php

declare(strict_types=1);

namespace App\Messenger;

use Closure;
use function is_string;
use function sprintf;
use function str_contains;
use function str_replace;
use function strpos;
use function substr;
use Symfony\Component\DependencyInjection\EnvVarProcessorInterface;
use Symfony\Component\DependencyInjection\Exception\RuntimeException;

/**
 * The DSN the variable carries, the lane in place of `{lane}`.
 *
 * A DSN without `{lane}` is refused: the lanes would share one queue, which the first receiver of the worker
 * drains under the retry policy of its own lane.
 */
final readonly class LaneDsn implements EnvVarProcessorInterface
{
    public const string PREFIX = 'beffroi_lane';

    public const string PLACEHOLDER = '{lane}';

    public static function of(string $lane, string $variable): string
    {
        return '%env(' . self::PREFIX . ':' . $lane . ':' . $variable . ')%';
    }

    /**
     * A refusal names the variable, never its value, which carries the credentials of a broker.
     *
     * @param string $name the lane, then the variable that carries the DSN, as of() writes them
     *
     * @throws RuntimeException
     */
    public function getEnv(string $prefix, string $name, Closure $getEnv): string
    {
        $separator = strpos($name, ':');
        if ($separator === false) {
            throw new RuntimeException(sprintf('Invalid env "%s:%s": the lane the DSN is read for comes first.', self::PREFIX, $name));
        }
        $variable = substr($name, $separator + 1);
        $dsn = $getEnv($variable);
        if (! is_string($dsn) || ! str_contains($dsn, self::PLACEHOLDER)) {
            throw new RuntimeException(sprintf(
                'The DSN of the lanes in "%s" names no "%s", so the four lanes of the provider would be one queue: write "%s" where the name of a queue goes, "queue_name=%s" in a DSN of the database, the name of the exchange on a broker.',
                $variable,
                self::PLACEHOLDER,
                self::PLACEHOLDER,
                self::PLACEHOLDER,
            ));
        }

        return str_replace(self::PLACEHOLDER, substr($name, 0, $separator), $dsn);
    }

    /**
     * @return array<string, string>
     */
    public static function getProvidedTypes(): array
    {
        return [self::PREFIX => 'string'];
    }
}
