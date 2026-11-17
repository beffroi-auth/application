<?php

declare(strict_types=1);

namespace App\Saml;

use App\Http\BoundedRead;
use App\Http\ByteLimit;
use App\Http\ReadRefusedException;
use App\Http\ReadWords;
use App\Http\Timeout;
use Beffroi\Saml\Domain\Metadata\MetadataLocation;
use Beffroi\Saml\Exception\MetadataRefusedException;
use Beffroi\Saml\Port\MetadataFetcherInterface;
use function fclose;
use function fopen;
use function is_file;
use function sprintf;
use function str_starts_with;
use function stream_get_contents;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final readonly class MetadataFetcher implements MetadataFetcherInterface
{
    /**
     * Seconds an https location has to answer whole (spec/saml.md section 7).
     */
    public const float TIMEOUT = 10.0;

    public function __construct(
        private HttpClientInterface $http,
        private float $timeout = self::TIMEOUT,
    ) {
    }

    public function fetch(MetadataLocation $location): string
    {
        return str_starts_with($location->value, 'https://') ? $this->download($location->value) : $this->read($location->value);
    }

    /**
     * @throws MetadataRefusedException
     */
    private function read(string $path): string
    {
        $file = is_file($path) ? @fopen($path, 'rb') : false;
        if ($file === false) {
            throw new MetadataRefusedException(sprintf('The file %s cannot be read.', $path));
        }
        $bytes = stream_get_contents($file, self::MAX_BYTES + 1);
        fclose($file);
        if ($bytes === false) {
            throw new MetadataRefusedException(sprintf('The file %s cannot be read.', $path));
        }

        return self::bounded($bytes, $path);
    }

    /**
     * @throws MetadataRefusedException
     */
    private function download(string $url): string
    {
        try {
            return new BoundedRead($this->http, new Timeout($this->timeout), new ByteLimit(self::MAX_BYTES))->read(
                $url,
                'application/samlmetadata+xml',
                self::words(),
            );
        } catch (ReadRefusedException $exception) {
            throw new MetadataRefusedException($exception->getMessage(), 0, $exception->getPrevious());
        }
    }

    /**
     * @throws MetadataRefusedException
     */
    private static function bounded(string $bytes, string $location): string
    {
        $limit = new ByteLimit(self::MAX_BYTES);
        if ($limit->isExceededBy($bytes)) {
            throw new MetadataRefusedException(self::words()->exceeded($location, $limit));
        }

        return $bytes;
    }

    private static function words(): ReadWords
    {
        return new ReadWords(
            'declare the URL the metadata are published at.',
            '%s holds more than %d bytes, which no metadata the loader accepts holds (spec/saml.md section 7).',
        );
    }
}
