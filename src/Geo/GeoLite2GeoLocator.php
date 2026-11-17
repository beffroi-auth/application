<?php

declare(strict_types=1);

namespace App\Geo;

use function is_array;
use function is_float;
use function is_int;
use function is_string;
use Beffroi\Core\Domain\Session\GeoLocation;
use Beffroi\Core\Port\GeoLocatorInterface;
use MaxMind\Db\Reader;
use Psr\Log\LoggerInterface;
use Throwable;

/**
 * The format's reader, not geoip2/geoip2, whose web service client would send addresses to a third party.
 *
 * It opens the file at every lookup: a worker runs for days, and the refresh replaces the database under it.
 */
final readonly class GeoLite2GeoLocator implements GeoLocatorInterface
{
    public function __construct(
        private GeoLite2Databases $databases,
        private LoggerInterface $logger,
    ) {
    }

    public function locate(string $ipAddress): ?GeoLocation
    {
        $city = $this->record(GeoLite2Databases::CITY, $ipAddress);
        $network = $this->record(GeoLite2Databases::ASN, $ipAddress);
        if ($city === [] && $network === []) {
            return null;
        }

        return $this->place($city, $network);
    }

    /**
     * @param array<string, mixed> $city
     * @param array<string, mixed> $network
     */
    private function place(array $city, array $network): ?GeoLocation
    {
        $coordinates = self::map($city, 'location');
        try {
            return new GeoLocation(
                self::text(self::map($city, 'country'), 'iso_code'),
                self::name(self::subdivision($city)),
                self::name(self::map($city, 'city')),
                self::number($network, 'autonomous_system_number'),
                self::text($network, 'autonomous_system_organization'),
                self::coordinate($coordinates, 'latitude'),
                self::coordinate($coordinates, 'longitude'),
            );
        } catch (Throwable $exception) {
            // An enrichment never fails the act it enriches: the place is lost and the address kept.
            $this->logger->warning('The databases of addresses answered a record this provider cannot read.', [
                'exception' => $exception,
            ]);

            return null;
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function record(string $edition, string $ipAddress): array
    {
        $path = $this->databases->path($edition);
        if ($path === null) {
            return [];
        }
        $reader = null;
        try {
            $reader = new Reader($path);

            return self::named($reader->get($ipAddress));
        } catch (Throwable $exception) {
            $this->logger->warning('The database of addresses "{edition}" cannot be read.', [
                'edition' => $edition,
                'exception' => $exception,
            ]);

            return [];
        } finally {
            $reader?->close();
        }
    }

    /**
     * The most specific: a country that nests two subdivisions lists the smaller one last.
     *
     * @param array<string, mixed> $city
     *
     * @return array<string, mixed>
     */
    private static function subdivision(array $city): array
    {
        $subdivisions = $city['subdivisions'] ?? null;
        if (! is_array($subdivisions)) {
            return [];
        }
        $last = [];
        foreach ($subdivisions as $subdivision) {
            if (is_array($subdivision)) {
                $last = self::named($subdivision);
            }
        }

        return $last;
    }

    /**
     * In English: no visitor's language is known on the lane, and every edition carries English.
     *
     * @param array<string, mixed> $place
     */
    private static function name(array $place): ?string
    {
        return self::text(self::map($place, 'names'), 'en');
    }

    /**
     * @param array<string, mixed> $record
     *
     * @return array<string, mixed>
     */
    private static function map(array $record, string $key): array
    {
        return self::named($record[$key] ?? null);
    }

    /**
     * @return array<string, mixed>
     */
    private static function named(mixed $document): array
    {
        if (! is_array($document)) {
            return [];
        }
        $named = [];
        foreach ($document as $key => $value) {
            $named[(string) $key] = $value;
        }

        return $named;
    }

    /**
     * @param array<string, mixed> $record
     */
    private static function text(array $record, string $key): ?string
    {
        $value = $record[$key] ?? null;

        return is_string($value) && $value !== '' ? $value : null;
    }

    /**
     * @param array<string, mixed> $record
     */
    private static function number(array $record, string $key): ?int
    {
        $value = $record[$key] ?? null;

        return is_int($value) ? $value : null;
    }

    /**
     * @param array<string, mixed> $record
     */
    private static function coordinate(array $record, string $key): ?float
    {
        $value = $record[$key] ?? null;

        return is_float($value) || is_int($value) ? (float) $value : null;
    }
}
