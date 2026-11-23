<?php

declare(strict_types=1);

namespace App\Tests\Image;

use App\Configuration\SecretFile;
use App\Kernel;
use App\Messenger\MessengerContract;
use function bin2hex;
use function file_get_contents;
use function file_put_contents;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use function preg_match_all;
use function preg_quote;
use function random_bytes;
use function sprintf;
use function sys_get_temp_dir;
use function unlink;

/**
 * Decision 0030
 */
final class EverySecretMayBeAFileTest extends TestCase
{
    #[Test]
    #[DataProvider('provideTheSecretsTheConfigurationReads')]
    public function theConfigurationOfProductionReadsTheSecretThroughItsFile(string $variable): void
    {
        // When
        $placeholders = self::placeholdersOf($variable);

        // Then
        static::assertNotSame([], $placeholders, sprintf('No configuration of production reads "%s".', $variable));
        foreach ($placeholders as $placeholder) {
            static::assertStringEndsWith(SecretFile::PREFIX . ':' . $variable . ')%', $placeholder);
        }
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function provideTheSecretsTheConfigurationReads(): iterable
    {
        yield 'the secret of the kernel' => ['APP_SECRET'];
        yield 'the database' => ['DATABASE_URL'];
        yield 'the key management service' => ['BEFFROI_KMS_DSN'];
        yield 'the key management service a migration moves the data keys to' => ['BEFFROI_KMS_DSN_SECOND'];
        yield 'the secrets that bind the data keys' => ['BEFFROI_DATA_KEY_SECRETS'];
        yield 'mail' => ['BEFFROI_MAIL_DSN'];
        yield 'SMS' => ['BEFFROI_SMS_DSN'];
        yield 'the licence of the address databases' => ['GEOIP_LICENSE_KEY'];
    }

    #[Test]
    #[DataProvider('provideTheLanes')]
    public function eachLaneReadsTheDsnThroughItsFile(string $lane): void
    {
        // When
        $transports = MessengerContract::configuration()['transports'] ?? null;

        // Then
        static::assertIsArray($transports);
        $transport = $transports[$lane] ?? null;
        static::assertIsArray($transport);
        $dsn = $transport['dsn'] ?? null;
        static::assertIsString($dsn);
        static::assertStringContainsString(SecretFile::PREFIX . ':' . MessengerContract::DSN_ENVIRONMENT_VARIABLE . ')%', $dsn);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function provideTheLanes(): iterable
    {
        foreach (MessengerContract::LANES as $lane) {
            yield $lane => [$lane];
        }
    }

    #[Test]
    public function theKernelOfProductionTakesItsSecretFromTheFileItsVariableNames(): void
    {
        // Given
        $path = sys_get_temp_dir() . '/beffroi-app-secret-' . bin2hex(random_bytes(8));
        file_put_contents($path, "the-secret-of-the-file\n");
        $variable = 'APP_SECRET' . SecretFile::SUFFIX;
        $_SERVER[$variable] = $_ENV[$variable] = $path;
        $kernel = new Kernel('prod', false);

        // When
        try {
            $kernel->boot();
            $secret = $kernel->getContainer()->getParameter('kernel.secret');
        } finally {
            $kernel->shutdown();
            unset($_SERVER[$variable], $_ENV[$variable]);
            unlink($path);
        }

        // Then
        static::assertSame('the-secret-of-the-file', $secret);
    }

    /**
     * @return list<string>
     */
    private static function placeholdersOf(string $variable): array
    {
        $placeholders = [];
        foreach (ConfigurationFile::everyOne() as $configuration) {
            if ($configuration->isOfAnotherEnvironment()) {
                continue;
            }
            $content = file_get_contents(ApplicationFiles::path($configuration->file));
            if ($content !== false && preg_match_all('/%env\((?:[^()%]*:)?' . preg_quote($variable, '/') . '\)%/', $content, $found) > 0) {
                $placeholders = [...$placeholders, ...$found[0]];
            }
        }

        return $placeholders;
    }
}
