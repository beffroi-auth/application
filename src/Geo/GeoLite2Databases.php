<?php

declare(strict_types=1);

namespace App\Geo;

use function bin2hex;
use function chmod;
use Closure;
use function copy;
use function file_get_contents;
use function file_put_contents;
use function in_array;
use function is_dir;
use function is_file;
use function is_readable;
use const LOCK_EX;
use function mkdir;
use function random_bytes;
use function rename;
use function sprintf;
use function trim;
use function unlink;
use Beffroi\Core\Exception\InvalidArgumentException;
use Beffroi\Core\Exception\RuntimeException;

final readonly class GeoLite2Databases
{
    public const string CITY = 'GeoLite2-City';

    public const string ASN = 'GeoLite2-ASN';

    /**
     * @var non-empty-list<string>
     */
    public const array EDITIONS = [self::CITY, self::ASN];

    public function __construct(
        private string $directory,
    ) {
    }

    /**
     * @throws InvalidArgumentException
     */
    public function path(string $edition): ?string
    {
        $path = $this->pathOf($edition);

        return is_file($path) && is_readable($path) ? $path : null;
    }

    /**
     * @throws InvalidArgumentException
     */
    public function version(string $edition): ?string
    {
        $version = @file_get_contents($this->pathOf($edition) . '.etag');

        return $version === false || trim($version) === '' ? null : trim($version);
    }

    /**
     * Copied and never read into a string: the city edition is fifty megabytes.
     *
     * @throws InvalidArgumentException
     * @throws RuntimeException
     */
    public function write(string $edition, string $file, ?string $version = null): void
    {
        $path = $this->pathOf($edition);
        if (! is_dir($this->directory) && ! @mkdir($this->directory, 0o700, true) && ! is_dir($this->directory)) {
            throw new RuntimeException(sprintf('The storage of the address databases "%s" cannot be created.', $this->directory));
        }
        $this->put(
            $path,
            static fn (string $temporary): bool => @copy($file, $temporary),
            sprintf('The database "%s" cannot be written to "%s".', $edition, $path),
        );
        // After the database, and removed when there is none: the ETag beside it is never the previous one's.
        if ($version === null) {
            @unlink($path . '.etag');

            return;
        }
        $this->put(
            $path . '.etag',
            static fn (string $temporary): bool => @file_put_contents($temporary, $version, LOCK_EX) !== false,
            sprintf('The version of the database "%s" cannot be written.', $edition),
        );
    }

    /**
     * @param Closure(string): bool $writes
     *
     * @throws RuntimeException
     */
    private function put(string $path, Closure $writes, string $refusal): void
    {
        $temporary = $path . '.' . bin2hex(random_bytes(8)) . '.tmp';
        if (! $writes($temporary) || ! @chmod($temporary, 0o600) || ! @rename($temporary, $path)) {
            @unlink($temporary);
            throw new RuntimeException($refusal);
        }
    }

    /**
     * @throws InvalidArgumentException
     */
    private function pathOf(string $edition): string
    {
        if (! in_array($edition, self::EDITIONS, true)) {
            throw new InvalidArgumentException(sprintf('"%s" is not an edition the enrichment of a provenance reads.', $edition));
        }

        return $this->directory . '/' . $edition . '.mmdb';
    }
}
