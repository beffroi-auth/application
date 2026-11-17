<?php

declare(strict_types=1);

namespace App\Geo;

use function fclose;
use function file_get_contents;
use function fopen;
use function fread;
use function fseek;
use function fstat;
use function fwrite;
use Generator;
use function gzopen;
use function implode;
use function intdiv;
use function iterator_count;
use function iterator_to_array;
use function min;
use function octdec;
use const PHP_INT_MAX;
use function rtrim;
use const SEEK_END;
use function str_contains;
use function str_ends_with;
use function stream_get_contents;
use function strlen;
use function substr;
use function trim;
use Beffroi\Core\Exception\InvalidArgumentException;

/**
 * Streamed, never read whole: the city edition's archive, decompressed, outgrows PHP's default memory limit.
 */
final readonly class GeoLite2Archive
{
    private const string GZIP_MAGIC = "\x1f\x8b";

    private const string MAXMIND_DB_MARKER = "\xAB\xCD\xEFMaxMind.com";

    /**
     * MaxMind DB File Format v2 §Database Metadata
     */
    private const int MAXMIND_DB_METADATA_MAX = 131_072;

    private const int TAR_BLOCK = 512;

    private const int CHUNK = 1_048_576;

    /**
     * @throws InvalidArgumentException
     */
    public static function unpack(string $archive, string $database): void
    {
        $from = @gzopen($archive, 'rb');
        if ($from === false) {
            throw new InvalidArgumentException('the file cannot be read');
        }
        $to = @fopen($database, 'w+b');
        try {
            if ($to === false) {
                throw new InvalidArgumentException('the database cannot be written where it is unpacked');
            }
            if (@file_get_contents($archive, length: 2) === self::GZIP_MAGIC) {
                self::member($from, $to);
            } else {
                // Read through the same stream, which hands what is not gzipped over as it stands.
                self::copied($from, $to, PHP_INT_MAX);
            }
            self::checked($to);
        } finally {
            fclose($from);
            if ($to !== false) {
                fclose($to);
            }
        }
    }

    /**
     * @param resource $tar
     * @param resource $database
     *
     * @throws InvalidArgumentException
     */
    private static function member($tar, $database): void
    {
        while (strlen($header = self::block($tar)) === self::TAR_BLOCK) {
            if (trim($header, "\0") === '') {
                break;
            }
            $name = rtrim(substr($header, 0, 100), "\0");
            $length = (int) octdec(trim(substr($header, 124, 12), "\0 "));
            if (str_ends_with($name, '.mmdb')) {
                self::copied($tar, $database, $length);
                // The rest of the stream is read too, because its check is at its end.
                iterator_count(self::chunks($tar, PHP_INT_MAX));

                return;
            }
            iterator_count(self::chunks($tar, intdiv($length + self::TAR_BLOCK - 1, self::TAR_BLOCK) * self::TAR_BLOCK));
        }

        throw new InvalidArgumentException('no member of the archive is a ".mmdb" database');
    }

    /**
     * @param resource $tar
     *
     * @throws InvalidArgumentException
     */
    private static function block($tar): string
    {
        return implode('', iterator_to_array(self::chunks($tar, self::TAR_BLOCK), false));
    }

    /**
     * @param resource $from
     * @param resource $to
     *
     * @throws InvalidArgumentException
     */
    private static function copied($from, $to, int $length): void
    {
        foreach (self::chunks($from, $length) as $chunk) {
            if (@fwrite($to, $chunk) !== strlen($chunk)) {
                throw new InvalidArgumentException('the database cannot be written where it is unpacked');
            }
        }
    }

    /**
     * @param resource $stream
     *
     * @return Generator<int, string>
     *
     * @throws InvalidArgumentException when the stream cannot be read: a gzip stream whose data or check does not hold
     */
    private static function chunks($stream, int $length): Generator
    {
        while ($length > 0) {
            $chunk = @fread($stream, min($length, self::CHUNK));
            if ($chunk === false) {
                throw new InvalidArgumentException('the archive is gzipped and cannot be unpacked');
            }
            if ($chunk === '') {
                return;
            }
            $length -= strlen($chunk);

            yield $chunk;
        }
    }

    /**
     * @param resource $database
     *
     * @throws InvalidArgumentException
     */
    private static function checked($database): void
    {
        $stat = fstat($database);
        $size = $stat === false ? 0 : $stat['size'];
        $tail = fseek($database, -min($size, self::MAXMIND_DB_METADATA_MAX), SEEK_END) === 0 ? stream_get_contents($database) : false;
        if ($tail === false || ! str_contains($tail, self::MAXMIND_DB_MARKER)) {
            throw new InvalidArgumentException('what it holds is not a MaxMind DB file');
        }
    }
}
