<?php

declare(strict_types=1);

namespace App\Geo;

use function bin2hex;
use DateTimeImmutable;
use function fclose;
use function fopen;
use function fwrite;
use function http_build_query;
use function in_array;
use function is_file;
use function is_readable;
use function preg_replace;
use function random_bytes;
use function sprintf;
use function str_contains;
use function str_starts_with;
use function strlen;
use function sys_get_temp_dir;
use function unlink;
use Beffroi\Core\Exception\InvalidArgumentException;
use MaxMind\Db\Reader;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;
use Throwable;

/**
 * MaxMind signs nothing: TLS and the licence key authenticate the source, and the file is checked by opening it.
 */
final readonly class GeoLite2Refresh
{
    public const string MAXMIND_DOWNLOAD = 'https://download.maxmind.com/app/geoip_download';

    /**
     * MaxMind redirects every download here, the one origin a redirect is followed to (decision 0032).
     */
    public const string MAXMIND_STORAGE = 'https://mm-prod-geoip-databases.a2649acb697e2c09b632799562c076f2.r2.cloudflarestorage.com/';

    public function __construct(
        private HttpClientInterface $http,
        private GeoLite2Databases $databases,
        private string $licenseKey = '',
    ) {
    }

    /**
     * @param list<string> $sources an https URL or a file, one archive or one database each
     *
     * @return list<GeoLite2Refreshment>
     */
    public function refresh(array $sources = []): array
    {
        if ($sources === [] && $this->licenseKey === '') {
            return [];
        }
        $refreshments = [];
        foreach ($sources === [] ? $this->maxmind() : $sources as $source) {
            $refreshments[] = $this->one($source);
        }

        return $refreshments;
    }

    private function one(string $source): GeoLite2Refreshment
    {
        $named = self::named($source);
        $edition = self::editionOf($source);
        $downloaded = self::temporary();
        $database = self::temporary();
        try {
            $fetched = $this->fetch($source, $edition === null ? null : $this->databases->version($edition), $downloaded);
            if ($fetched === null) {
                return GeoLite2Refreshment::unchanged($named);
            }
            [$archive, $version] = $fetched;
            GeoLite2Archive::unpack($archive, $database);
            [$declared, $builtOn, $nodes] = self::read($database);
            $this->databases->write($declared, $database, $version);

            return GeoLite2Refreshment::stored($named, $declared, $builtOn, $nodes);
        } catch (Throwable $exception) {
            return GeoLite2Refreshment::refused($named, self::withoutQueries($exception->getMessage()));
        } finally {
            @unlink($downloaded);
            @unlink($database);
        }
    }

    /**
     * @return array{string, DateTimeImmutable, int}
     *
     * @throws InvalidArgumentException when it cannot be opened, or is not an edition this provider reads
     */
    private static function read(string $database): array
    {
        try {
            $reader = new Reader($database);
            $metadata = $reader->metadata();
            $reader->close();
        } catch (Throwable $exception) {
            throw new InvalidArgumentException(sprintf('the database cannot be opened (%s)', $exception->getMessage()), previous: $exception);
        }
        if (! in_array($metadata->databaseType, GeoLite2Databases::EDITIONS, true)) {
            throw new InvalidArgumentException(sprintf(
                'it is a "%s" database, which the enrichment of a provenance does not read',
                $metadata->databaseType,
            ));
        }

        return [$metadata->databaseType, new DateTimeImmutable('@' . $metadata->buildEpoch), $metadata->nodeCount];
    }

    /**
     * @return array{string, ?string}|null
     *
     * @throws InvalidArgumentException
     */
    private function fetch(string $source, ?string $version, string $into): ?array
    {
        if (! str_starts_with($source, 'https://')) {
            // A file only: a stream wrapper would reach the network without the client's redirect refusal.
            if (! is_file($source)) {
                throw new InvalidArgumentException('it is neither an https URL nor a file of this host');
            }

            return is_readable($source) ? [$source, null] : throw new InvalidArgumentException('the file cannot be read');
        }
        try {
            $response = $this->requested($source, $version);
            if (self::redirects($response)) {
                $response = $this->requested(self::followed($source, self::location($response)), $version);
                if (self::redirects($response)) {
                    throw new InvalidArgumentException(sprintf(
                        'the storage of MaxMind redirects again, to %s, and no outbound request follows a second redirect',
                        self::location($response),
                    ));
                }
            }
            $status = $response->getStatusCode();
            if ($status === 304) {
                return null;
            }
            if ($status !== 200) {
                throw new InvalidArgumentException(sprintf('the source answered %d', $status));
            }
            $this->written($response, $into);

            return [$into, $response->getHeaders(false)['etag'][0] ?? null];
        } catch (InvalidArgumentException $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            throw new InvalidArgumentException(sprintf('the source cannot be reached: %s', $exception->getMessage()), previous: $exception);
        }
    }

    private function requested(string $url, ?string $version): ResponseInterface
    {
        return $this->http->request('GET', $url, [
            'headers' => $version === null
                ? []
                : [
                    'If-None-Match' => $version,
                ],
            'max_redirects' => 0,
            'buffer' => false,
        ]);
    }

    /**
     * @throws InvalidArgumentException
     */
    private static function followed(string $source, string $location): string
    {
        if (! str_starts_with($source, self::MAXMIND_DOWNLOAD . '?')) {
            throw new InvalidArgumentException(sprintf(
                'the source redirects to %s, and no outbound request follows a redirect; name that URL instead',
                $location,
            ));
        }
        if (! str_starts_with($location, self::MAXMIND_STORAGE)) {
            throw new InvalidArgumentException(sprintf(
                'MaxMind redirects to %s rather than to %s, the one redirect an outbound request follows',
                $location,
                self::MAXMIND_STORAGE,
            ));
        }

        return $location;
    }

    /**
     * @throws InvalidArgumentException
     */
    private function written(ResponseInterface $response, string $into): void
    {
        $file = @fopen($into, 'wb');
        if ($file === false) {
            throw new InvalidArgumentException('the archive cannot be written where it is downloaded');
        }
        try {
            foreach ($this->http->stream($response) as $chunk) {
                $content = $chunk->getContent();
                if (@fwrite($file, $content) !== strlen($content)) {
                    throw new InvalidArgumentException('the archive cannot be written where it is downloaded');
                }
            }
        } finally {
            fclose($file);
        }
    }

    private static function redirects(ResponseInterface $response): bool
    {
        $status = $response->getStatusCode();

        return $status >= 300 && $status < 400 && $status !== 304;
    }

    private static function location(ResponseInterface $response): string
    {
        return $response->getHeaders(false)['location'][0] ?? '?';
    }

    /**
     * The query of a URL carries the licence key or a presigned signature, and a refusal is logged.
     */
    private static function withoutQueries(string $reason): string
    {
        return preg_replace('~(https?://[^\s"\'<>?#]*)([?#])[^\s"\'<>]*?(?=[.,;:)\]]*(?:[\s"\'<>]|$))~', '$1$2...', $reason) ?? $reason;
    }

    private static function temporary(): string
    {
        return sys_get_temp_dir() . '/beffroi-geoip-' . bin2hex(random_bytes(8));
    }

    /**
     * @return list<string>
     */
    private function maxmind(): array
    {
        $sources = [];
        foreach (GeoLite2Databases::EDITIONS as $edition) {
            $sources[] =
                self::MAXMIND_DOWNLOAD
                . '?'
                . http_build_query([
                    'edition_id' => $edition,
                    'license_key' => $this->licenseKey,
                    'suffix' => 'tar.gz',
                ]);
        }

        return $sources;
    }

    private static function editionOf(string $source): ?string
    {
        foreach (GeoLite2Databases::EDITIONS as $edition) {
            if (str_contains($source, $edition)) {
                return $edition;
            }
        }

        return null;
    }

    /**
     * The source without the licence key: what a console writes ends up in logs and terminal histories.
     */
    private static function named(string $source): string
    {
        if (! str_starts_with($source, self::MAXMIND_DOWNLOAD)) {
            return $source;
        }
        $edition = self::editionOf($source);

        return self::MAXMIND_DOWNLOAD . ($edition === null ? '' : '?edition_id=' . $edition);
    }
}
