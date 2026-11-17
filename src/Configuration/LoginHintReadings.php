<?php

declare(strict_types=1);

namespace App\Configuration;

use function array_keys;
use function array_map;
use Beffroi\Oidc\Application\Hint\SubjectLoginHint;
use Beffroi\Oidc\Application\Hint\UsernameLoginHint;
use Beffroi\Oidc\Application\Hint\UserHintInterface;
use Generator;
use function implode;
use function in_array;
use IteratorAggregate;
use function sprintf;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\DependencyInjection\Loader\Configurator\InlineServiceConfigurator;
use function Symfony\Component\DependencyInjection\Loader\Configurator\inline_service;
use function Symfony\Component\DependencyInjection\Loader\Configurator\service;
use function Symfony\Component\DependencyInjection\Loader\Configurator\service_locator;
use function Symfony\Component\DependencyInjection\Loader\Configurator\tagged_iterator;
use Symfony\Contracts\Service\ServiceProviderInterface;

/**
 * @implements IteratorAggregate<int, UserHintInterface>
 */
final readonly class LoginHintReadings implements IteratorAggregate
{
    /**
     * CIBA Core §15
     */
    public const string DEFAULT = 'sub';

    private const string NONE = 'none';

    /**
     * @var array<string, class-string<UserHintInterface>>
     */
    private const array READINGS = [
        'sub' => SubjectLoginHint::class,
        'username' => UsernameLoginHint::class,
    ];

    /**
     * @param iterable<UserHintInterface> $tagged
     * @param ServiceProviderInterface<UserHintInterface> $readings
     *
     * @throws InvalidConfigurationException
     */
    public function __construct(
        private iterable $tagged,
        private string $name,
        private ServiceProviderInterface $readings,
        string $key,
    ) {
        if (! in_array($name, self::names(), true)) {
            throw new InvalidConfigurationException(sprintf(
                'The "beffroi" configuration key "%s" names one of %s, "%s" given.',
                $key,
                implode(', ', array_map(static fn (string $known): string => '"' . $known . '"', self::names())),
                $name,
            ));
        }
    }

    /**
     * @return list<string>
     */
    public static function names(): array
    {
        return [...array_keys(self::READINGS), self::NONE];
    }

    public static function wired(string $tag, string $name, string $key): InlineServiceConfigurator
    {
        return inline_service(self::class)->args([
            tagged_iterator($tag),
            $name,
            service_locator(array_map(service(...), self::READINGS)),
            $key,
        ]);
    }

    /**
     * @return Generator<int, UserHintInterface>
     */
    public function getIterator(): Generator
    {
        foreach ($this->tagged as $reading) {
            yield $reading;
        }
        if ($this->name === self::NONE) {
            return;
        }
        yield $this->readings->get($this->name);
    }
}
