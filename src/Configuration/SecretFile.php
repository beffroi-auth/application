<?php

declare(strict_types=1);

namespace App\Configuration;

use Closure;
use function explode;
use function file_get_contents;
use function in_array;
use function is_string;
use function rtrim;
use function sprintf;
use Symfony\Component\DependencyInjection\EnvVarProcessorInterface;
use Symfony\Component\DependencyInjection\Exception\EnvNotFoundException;
use Symfony\Component\DependencyInjection\Exception\RuntimeException;

/**
 * A secret a deployment names by its variable, or by the path of a file in the variable of the same name ending in
 * `_FILE`, where Swarm and Compose mount a secret. Naming both is refused; a value the `.env` files of the image give
 * is no naming, so the file a deployment names replaces it.
 */
final readonly class SecretFile implements EnvVarProcessorInterface
{
    public const string PREFIX = 'beffroi_secret';

    public const string SUFFIX = '_FILE';

    /**
     * Where Symfony's Dotenv lists the variables it gave from the `.env` files.
     */
    private const string FROM_THE_ENV_FILES = 'SYMFONY_DOTENV_VARS';

    /**
     * A refusal names the variables and the path, never the secret.
     *
     * @throws EnvNotFoundException when neither the variable nor its file is named
     * @throws RuntimeException when both are, or when the file cannot be read
     */
    public function getEnv(string $prefix, string $name, Closure $getEnv): mixed
    {
        $file = $name . self::SUFFIX;
        $path = self::named($file, $getEnv);
        if ($path === null) {
            return $getEnv($name);
        }
        if (self::namedByTheDeployment($name, $getEnv)) {
            throw new RuntimeException(sprintf('"%s" and "%s" are both set: a secret is named by one of them.', $name, $file));
        }
        $secret = @file_get_contents($path);
        if ($secret === false) {
            throw new RuntimeException(sprintf('"%s" names "%s", which cannot be read.', $file, $path));
        }

        // A file written by echo ends in a line break that is no part of the secret.
        return rtrim($secret, "\r\n");
    }

    /**
     * @return array<string, string>
     */
    public static function getProvidedTypes(): array
    {
        return [self::PREFIX => 'string'];
    }

    private static function named(string $variable, Closure $getEnv): ?string
    {
        try {
            $value = $getEnv($variable);
        } catch (EnvNotFoundException) {
            return null;
        }

        return is_string($value) && $value !== '' ? $value : null;
    }

    private static function namedByTheDeployment(string $variable, Closure $getEnv): bool
    {
        return self::named($variable, $getEnv) !== null && ! in_array($variable, explode(',', self::named(self::FROM_THE_ENV_FILES, $getEnv) ?? ''), true);
    }
}
